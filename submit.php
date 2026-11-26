<?php
/*
 * POST api/submit.php  (JSON body)
 * Saves the result of one level, logs anonymous right/wrong attempts,
 * gives badges, and returns the progress report for that level.
 *
 * Games 1 and 2:  { game, level, results: [ {item_id, correct}, ... ] }
 * Game 3:         { game, level, build_mistakes, build_items: [ {slug, correct} ],
 *                   scenarios: [ {id, wrong} ] }
 */
define('ROOT', '../');
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'Use POST.'], 405);
}
$me = current_user();
if (!$me || $me['role'] !== 'learner') {
    json_out(['error' => 'Please log in as a learner.'], 401);
}
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    json_out(['error' => 'Bad request.'], 400);
}
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($in['csrf'] ?? '');
if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
    json_out(['error' => 'Your session expired. Refresh the page.'], 400);
}

$game = (int) ($in['game'] ?? 0);
$level = (int) ($in['level'] ?? 0);
if ($game < 1 || $game > 3 || $level < 1 || $level > 3) {
    json_out(['error' => 'Unknown game or level.'], 400);
}
if (!in_array($level, unlocked_levels($me['id'], $game), true)) {
    json_out(['error' => 'This level is locked.'], 403);
}

$attempts = [];   // [ref_type, ref_id, is_correct]
$ctx = [];

if ($game === 1 || $game === 2) {
    $results = $in['results'] ?? [];
    if (!is_array($results) || count($results) === 0 || count($results) > 40) {
        json_out(['error' => 'No answers were sent.'], 400);
    }
    $ids = [];
    foreach ($results as $r) {
        $ids[] = (int) ($r['item_id'] ?? 0);
    }
    $ids = array_values(array_unique($ids));
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $known = array_map('intval', array_column(q_all("SELECT id FROM items WHERE id IN ($marks)", $ids), 'id'));
    $correct = 0;
    $total = 0;
    foreach ($results as $r) {
        $id = (int) ($r['item_id'] ?? 0);
        if (!in_array($id, $known, true)) {
            continue;
        }
        $ok = !empty($r['correct']);
        $total++;
        if ($ok) {
            $correct++;
        }
        $attempts[] = ['item', $id, $ok ? 1 : 0];
    }
    if ($total === 0) {
        json_out(['error' => 'No valid answers were sent.'], 400);
    }
    $score = (int) round($correct / $total * 100);
    $mistakes = $total - $correct;
} else {
    // Game 3: build phase is worth 50 points, troubleshooting 50 points.
    $bm = max(0, min(50, (int) ($in['build_mistakes'] ?? 0)));
    $buildItems = is_array($in['build_items'] ?? null) ? $in['build_items'] : [];
    $scen = is_array($in['scenarios'] ?? null) ? $in['scenarios'] : [];

    $slugToId = [];
    foreach (q_all('SELECT id, slug FROM items') as $r) {
        $slugToId[$r['slug']] = (int) $r['id'];
    }
    $tray = g3_tray($level);
    $correct = 0;
    $total = 0;
    foreach ($buildItems as $b) {
        $slug = (string) ($b['slug'] ?? '');
        if (!in_array($slug, $tray, true)) {
            continue;
        }
        $ok = !empty($b['correct']);
        $total++;
        if ($ok) {
            $correct++;
        }
        if (isset($slugToId[$slug])) {
            $attempts[] = ['item', $slugToId[$slug], $ok ? 1 : 0];
        }
    }

    $validScen = array_map('intval', array_column(q_all('SELECT id FROM scenarios WHERE level = ?', [$level]), 'id'));
    $scenResults = [];
    foreach (array_slice($scen, 0, 5) as $s) {
        $id = (int) ($s['id'] ?? 0);
        if (in_array($id, $validScen, true) && !isset($scenResults[$id])) {
            $scenResults[$id] = max(0, (int) ($s['wrong'] ?? 0));
        }
    }
    $n = count($scenResults);
    $factor = function ($wrong) {
        return [1, 0.6, 0.3][$wrong] ?? 0.1;
    };
    if ($n === 0) {
        $score = max(0, 100 - 20 * $bm);
    } else {
        $points = max(0, 50 - 10 * $bm);
        foreach ($scenResults as $id => $wrong) {
            $points += (50 / $n) * $factor($wrong);
            $total++;
            if ($wrong === 0) {
                $correct++;
            }
            $attempts[] = ['scenario', $id, $wrong === 0 ? 1 : 0];
        }
        $score = (int) round($points);
    }
    $mistakes = $bm + array_sum($scenResults);
    $ctx['fixer'] = $n > 0 && max($scenResults) === 0;
}

$score = max(0, min(100, $score));
$prevUnlocked = unlocked_levels($me['id'], $game);

$pdo = db();
$pdo->beginTransaction();
try {
    q('INSERT INTO scores (user_id, game, level, score, correct, total, mistakes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$me['id'], $game, $level, $score, $correct, $total, $mistakes, now()]);
    foreach ($attempts as $a) {
        q('INSERT INTO attempts (ref_type, ref_id, game, level, is_correct, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$a[0], $a[1], $game, $level, $a[2], now()]);
    }
    $newBadges = award_badges($me['id'], $ctx);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_out(['error' => 'Could not save your score. Please try again.'], 500);
}

// Progress report for this level (calculated by the server from stored scores).
$best = best_scores($me['id']);
$base = baseline($me['id'], $game);
$current = game_current($best, $game);
$gain = normalized_gain($current, $base);
$nowUnlocked = unlocked_levels($me['id'], $game, $best);
$nextLevel = $level < 3 ? $level + 1 : null;
$p = progress_summary($me['id']);

$badgeOut = [];
foreach ($newBadges as $k) {
    $b = badges()[$k];
    $badgeOut[] = ['key' => $k, 'name' => $b['name'], 'desc' => $b['desc'], 'svg' => badge_svg($k, 72)];
}

json_out([
    'ok' => true,
    'score' => $score,
    'correct' => $correct,
    'total' => $total,
    'mistakes' => $mistakes,
    'passed' => $score >= PASS_MARK,
    'passMark' => PASS_MARK,
    'best' => $best[$game][$level] ?? $score,
    'baseline' => $base,
    'current' => $current,
    'gain' => $gain,
    'gainText' => gain_text($gain, $base),
    'nextLevel' => $nextLevel,
    'nextUnlocked' => $nextLevel !== null && in_array($nextLevel, $nowUnlocked, true),
    'justUnlocked' => $nextLevel !== null && in_array($nextLevel, $nowUnlocked, true) && !in_array($nextLevel, $prevUnlocked, true),
    'newBadges' => $badgeOut,
    'allPlayed' => $p['all_played'],
    'everyGameStarted' => $p['every_game_started'],
]);
