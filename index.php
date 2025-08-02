<?php
session_start();

// 存储排行榜的文件
define('RANKING_FILE', 'ranking.txt');

// 生成数独网格
function generate_sudoku() {
    // 生成一个完整的数独解决方案
    $solution = generate_sudoku_solution();

    // 复制解决方案作为初始板
    $board = $solution;

    // 移除数字创建谜题（非唯一解模式 - 移除更多数字）
    $cells_to_remove = 60; // 移除60个数字
    $removed = 0;

    while ($removed < $cells_to_remove) {
        $row = rand(0, 8);
        $col = rand(0, 8);

        if ($board[$row][$col] != 0) {
            $board[$row][$col] = 0;
            $removed++;
        }
    }

    return ['board' => $board, 'solution' => $solution];
}

// 生成完整的数独解决方案
function generate_sudoku_solution() {
    $grid = array_fill(0, 9, array_fill(0, 9, 0));

    // 填充对角线上的3x3格子
    fill_diagonal_boxes($grid);

    // 填充剩余格子
    fill_remaining(0, 3, $grid);

    return $grid;
}

// 填充对角线上的3x3格子
function fill_diagonal_boxes(&$grid) {
    for ($i = 0; $i < 9; $i += 3) {
        fill_box($grid, $i, $i);
    }
}

// 填充一个3x3格子
function fill_box(&$grid, $row, $col) {
    $nums = range(1, 9);
    shuffle($nums);

    for ($i = 0; $i < 3; $i++) {
        for ($j = 0; $j < 3; $j++) {
            $grid[$row + $i][$col + $j] = array_pop($nums);
        }
    }
}

// 递归填充剩余格子
function fill_remaining($i, $j, &$grid) {
    if ($j >= 9 && $i < 8) {
        $i += 1;
        $j = 0;
    }

    if ($i >= 9 && $j >= 9) {
        return true;
    }

    if ($i < 3) {
        if ($j < 3) $j = 3;
    } elseif ($i < 6) {
        if ($j == (int)($i / 3) * 3) $j += 3;
    } else {
        if ($j == 6) {
            $i += 1;
            $j = 0;
            if ($i >= 9) return true;
        }
    }

    for ($num = 1; $num <= 9; $num++) {
        if (is_safe($grid, $i, $j, $num)) {
            $grid[$i][$j] = $num;

            if (fill_remaining($i, $j + 1, $grid)) {
                return true;
            }

            $grid[$i][$j] = 0;
        }
    }

    return false;
}

// 检查数字是否可以安全放置
function is_safe($grid, $row, $col, $num) {
    // 检查行
    for ($i = 0; $i < 9; $i++) {
        if ($grid[$row][$i] == $num) return false;
    }

    // 检查列
    for ($i = 0; $i < 9; $i++) {
        if ($grid[$i][$col] == $num) return false;
    }

    // 检查3x3格子
    $startRow = $row - $row % 3;
    $startCol = $col - $col % 3;

    for ($i = 0; $i < 3; $i++) {
        for ($j = 0; $j < 3; $j++) {
            if ($grid[$startRow + $i][$startCol + $j] == $num) return false;
        }
    }

    return true;
}

// 获取排行榜数据
function get_ranking() {
    if (file_exists(RANKING_FILE)) {
        $ranking = file(RANKING_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $ranking_data = [];

        foreach ($ranking as $line) {
            list($time, $name) = explode('|', $line);
            $ranking_data[] = [
                'time' => (int)$time,
                'name' => $name
            ];
        }

        // 按时间排序
        usort($ranking_data, function($a, $b) {
            return $a['time'] - $b['time'];
        });

        return array_slice($ranking_data, 0, 10); // 返回前10名
    }
    return [];
}

// 添加新的排行榜条目
function add_to_ranking($time, $name) {
    $name = substr(trim($name), 0, 15); // 限制名字长度
    $entry = $time . '|' . $name . PHP_EOL;
    file_put_contents(RANKING_FILE, $entry, FILE_APPEND);
}

// 初始化游戏
if (empty($_SESSION['sudoku'])) {
    $sudoku = generate_sudoku();
    $_SESSION['sudoku'] = $sudoku;
    $_SESSION['start_time'] = time();
    $_SESSION['paused_time'] = 0;
    $_SESSION['paused'] = false;
    $_SESSION['pause_start'] = 0;
    $_SESSION['user_board'] = $sudoku['board'];
    $_SESSION['notes'] = array_fill(0, 9, array_fill(0, 9, []));
    $_SESSION['cheated'] = false; // 标记是否查看了答案
    $_SESSION['note_mode'] = false; // 标记模式状态
}

// 处理用户输入
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cell'])) {
        list($row, $col) = explode(',', $_POST['cell']);
        $row = (int)$row;
        $col = (int)$col;

        if (isset($_POST['value'])) {
            $value = (int)$_POST['value'];
            if ($value >= 1 && $value <= 9) {
                $_SESSION['user_board'][$row][$col] = $value;
            }
        } elseif (isset($_POST['clear'])) {
            $_SESSION['user_board'][$row][$col] = 0;
        } elseif (isset($_POST['note'])) {
            $note = (int)$_POST['note'];
            if ($note >= 1 && $note <= 9) {
                $key = array_search($note, $_SESSION['notes'][$row][$col]);
                if ($key === false) {
                    $_SESSION['notes'][$row][$col][] = $note;
                } else {
                    unset($_SESSION['notes'][$row][$col][$key]);
                }
                sort($_SESSION['notes'][$row][$col]);
            }
        }
    } elseif (isset($_POST['reset'])) {
        $_SESSION['user_board'] = $_SESSION['sudoku']['board'];
        $_SESSION['notes'] = array_fill(0, 9, array_fill(0, 9, []));
        $_SESSION['start_time'] = time();
        $_SESSION['paused_time'] = 0;
        $_SESSION['paused'] = false;
        $_SESSION['cheated'] = false;
    } elseif (isset($_POST['new_game'])) {
        $sudoku = generate_sudoku();
        $_SESSION['sudoku'] = $sudoku;
        $_SESSION['user_board'] = $sudoku['board'];
        $_SESSION['notes'] = array_fill(0, 9, array_fill(0, 9, []));
        $_SESSION['start_time'] = time();
        $_SESSION['paused_time'] = 0;
        $_SESSION['paused'] = false;
        $_SESSION['cheated'] = false;
    } elseif (isset($_POST['submit'])) {
        // 检查是否查看了答案
        if ($_SESSION['cheated']) {
            // 如果查看了答案，不保存成绩，直接重定向
            unset($_SESSION['sudoku']);
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        }
        
        $elapsed = time() - $_SESSION['start_time'] - $_SESSION['paused_time'];
        $name = $_POST['player_name'] ?? '匿名';

        add_to_ranking($elapsed, $name);
        unset($_SESSION['sudoku']);
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } elseif (isset($_POST['toggle_pause'])) {
        if ($_SESSION['paused']) {
            // 恢复游戏 - 计算暂停时间
            $_SESSION['paused_time'] += time() - $_SESSION['pause_start'];
            $_SESSION['paused'] = false;
        } else {
            // 暂停游戏 - 记录暂停开始时间
            $_SESSION['pause_start'] = time();
            $_SESSION['paused'] = true;
        }
    } elseif (isset($_POST['show_answer'])) {
        // 标记用户查看了答案
        $_SESSION['cheated'] = true;
        // 填充所有空白单元格的答案
        $_SESSION['user_board'] = $_SESSION['sudoku']['solution'];
    } elseif (isset($_POST['toggle_note_mode'])) {
        // 切换标记模式
        $_SESSION['note_mode'] = !$_SESSION['note_mode'];
    }
}

// 获取当前游戏状态
$sudoku = $_SESSION['sudoku'];
$user_board = $_SESSION['user_board'];
$notes = $_SESSION['notes'];
$start_time = $_SESSION['start_time'];
$paused = $_SESSION['paused'];
$cheated = $_SESSION['cheated'];
$paused_time = $_SESSION['paused_time'];
$note_mode = $_SESSION['note_mode'];

// 计算已用时间
if ($paused) {
    $elapsed_time = $_SESSION['pause_start'] - $start_time - $paused_time;
} else {
    $elapsed_time = time() - $start_time - $paused_time;
}

$minutes = floor($elapsed_time / 60);
$seconds = $elapsed_time % 60;
$formatted_time = sprintf("%02d:%02d", $minutes, $seconds);

// 获取排行榜
$ranking = get_ranking();

// 检查游戏是否完成
$completed = true;
for ($i = 0; $i < 9; $i++) {
    for ($j = 0; $j < 9; $j++) {
        if ($user_board[$i][$j] == 0) {
            $completed = false;
            break 2;
        }
    }
}

// 深色模式设置
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数独练习</title>
    <style>
        :root {
            --bg-color: #f5f5f5;
            --text-color: #333;
            --cell-bg: #fff;
            --border-color: #ccc;
            --fixed-cell-bg: #e9e9e9;
            --fixed-cell-color: #000;
            --highlight-bg: #e6f7ff;
            --note-color: #666;
            --button-bg: #4a86e8;
            --button-hover: #3a76d8;
            --button-color: #fff;
            --grid-border: 2px solid #333;
            --section-bg: #fff;
            --section-border: 1px solid #ddd;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            --submit-button-bg: #27ae60;
            --submit-button-hover: #219653;
            --pause-button-bg: #f39c12;
            --pause-button-hover: #e67e22;
            --cheated-color: #e74c3c;
            --note-mode-bg: #9b59b6;
            --note-mode-hover: #8e44ad;
            --disabled-bg: #95a5a6;
            --disabled-hover: #7f8c8d;
        }

        .dark-mode {
            --bg-color: #1a1a1a;
            --text-color: #f0f0f0;
            --cell-bg: #2d2d2d;
            --border-color: #444;
            --fixed-cell-bg: #1e1e1e;
            --fixed-cell-color: #f0f0f0;
            --highlight-bg: #2a4365;
            --note-color: #aaa;
            --button-bg: #3a76d8;
            --button-hover: #4a86e8;
            --section-bg: #222;
            --section-border: 1px solid #444;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            --submit-button-bg: #2ecc71;
            --submit-button-hover: #27ae60;
            --pause-button-bg: #f1c40f;
            --pause-button-hover: #f39c12;
            --note-mode-bg: #8e44ad;
            --note-mode-hover: #7d3c98;
            --disabled-bg: #555;
            --disabled-hover: #444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            padding: 1rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        h1 {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }

        .subtitle {
            font-size: 1.1rem;
            color: #666;
        }

        .dark-mode .subtitle {
            color: #aaa;
        }

        .container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            flex: 1;
        }

        @media (min-width: 768px) {
            .container {
                grid-template-columns: 1fr 300px;
            }
        }

        .game-section {
            background: var(--section-bg);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            border: var(--section-border);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
            justify-content: center;
            align-items: center;
        }

        .timer {
            font-size: 1.5rem;
            font-weight: bold;
            background: var(--cell-bg);
            padding: 0.5rem 1rem;
            border-radius: 5px;
            min-width: 120px;
            text-align: center;
            box-shadow: var(--box-shadow);
        }

        .paused .timer {
            color: #e74c3c;
        }

        button {
            background: var(--button-bg);
            color: var(--button-color);
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        button:hover {
            background: var(--button-hover);
        }

        button:disabled {
            background: var(--disabled-bg);
            cursor: not-allowed;
        }

        button:disabled:hover {
            background: var(--disabled-bg);
        }

        .submit-btn {
            background: var(--submit-button-bg);
        }

        .submit-btn:hover {
            background: var(--submit-button-hover);
        }

        .submit-btn:disabled {
            background: var(--disabled-bg);
        }

        .pause-btn {
            background: var(--pause-button-bg);
        }

        .pause-btn:hover {
            background: var(--pause-button-hover);
        }

        .note-mode-btn {
            background: var(--note-mode-bg);
        }

        .note-mode-btn:hover {
            background: var(--note-mode-hover);
        }

        .note-mode-btn.active {
            box-shadow: 0 0 0 3px rgba(155, 89, 182, 0.5);
        }

        .dark-mode .note-mode-btn.active {
            box-shadow: 0 0 0 3px rgba(142, 68, 173, 0.7);
        }

        .button-group {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .sudoku-container {
            display: flex;
            justify-content: center;
            margin: 0 auto;
            width: 100%;
            max-width: 500px;
            position: relative;
        }

        .sudoku-grid {
            display: grid;
            grid-template-columns: repeat(9, 1fr);
            gap: 0;
            border: var(--grid-border);
            width: 100%;
            max-width: 500px;
            aspect-ratio: 1/1;
            position: relative;
        }

        .cell {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--cell-bg);
            border: 1px solid var(--border-color);
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
            aspect-ratio: 1/1;
        }

        .cell.fixed {
            background: var(--fixed-cell-bg);
            color: var(--fixed-cell-color);
            cursor: default;
        }

        .cell.highlighted {
            background: var(--highlight-bg);
        }

        .cell.note-mode-hover:hover {
            background: rgba(155, 89, 182, 0.2);
        }

        .cell:nth-child(3n):not(:nth-child(9n)) {
            border-right: 2px solid var(--text-color);
        }

        .cell:nth-child(n+19):nth-child(-n+27),
        .cell:nth-child(n+46):nth-child(-n+54) {
            border-bottom: 2px solid var(--text-color);
        }

        /* 优化标记显示 - 使用紧凑布局 */
        .notes {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-wrap: wrap;
            padding: 0.1rem;
            pointer-events: none;
            font-size: 0.75rem;
            align-content: flex-start;
        }

        .note {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--note-color);
            width: 33.33%;
            height: 33.33%;
            box-sizing: border-box;
            position: relative;
        }

        /* 标记数字样式优化 - 简洁版本 */
        .note-value {
            display: inline-block;
            text-align: center;
            color: var(--note-color);
            font-size: 0.7rem;
            font-weight: bold;
        }

        .number-pad {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.5rem;
            max-width: 300px;
            margin: 1.5rem auto 0;
        }

        .number-btn {
            aspect-ratio: 1/1;
            font-size: 1.2rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--cell-bg);
            border: 1px solid var(--border-color);
            border-radius: 5px;
            cursor: pointer;
        }

        .number-btn:hover {
            background: var(--highlight-bg);
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .panel {
            background: var(--section-bg);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            border: var(--section-border);
        }

        .panel h2 {
            margin-bottom: 1rem;
            font-size: 1.4rem;
            border-bottom: 2px solid var(--button-bg);
            padding-bottom: 0.5rem;
        }

        .ranking-list {
            list-style: none;
        }

        .ranking-item {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px dashed var(--border-color);
        }

        .ranking-item:first-child {
            font-weight: bold;
            color: gold;
            font-size: 1.1rem;
        }

        .ranking-item:nth-child(2) {
            color: silver;
        }

        .ranking-item:nth-child(3) {
            color: #cd7f32; /* bronze */
        }

        .cheated-entry {
            color: var(--cheated-color);
            font-style: italic;
        }

        .theme-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: var(--button-bg);
            color: var(--button-color);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 100;
        }

        footer {
            text-align: center;
            margin-top: 2rem;
            padding: 1rem;
            border-top: 1px solid var(--border-color);
        }

        footer a {
            color: var(--button-bg);
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--section-bg);
            border-radius: 10px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .modal h2 {
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border-radius: 5px;
            border: 1px solid var(--border-color);
            background: var(--cell-bg);
            color: var(--text-color);
            font-size: 1rem;
        }

        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .cheated-warning {
            color: var(--cheated-color);
            text-align: center;
            margin-top: 1rem;
            font-weight: bold;
        }

        .completion-message {
            text-align: center;
            margin: 1rem 0;
            font-weight: bold;
            color: var(--submit-button-bg);
        }

        .paused-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            z-index: 10;
            border-radius: 10px;
        }

        .cheated .main-value:not(.fixed) {
            color: var(--cheated-color);
        }

        .note-mode-instruction {
            text-align: center;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: var(--note-mode-bg);
            font-weight: bold;
        }

        .compact-notes {
            position: absolute;
            top: 2px;
            left: 2px;
            right: 2px;
            bottom: 2px;
            display: flex;
            flex-wrap: wrap;
            align-content: flex-start;
            padding: 1px;
        }

        .compact-note {
            width: 30%;
            height: 30%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .submit-disabled-tooltip {
            position: relative;
            display: inline-block;
        }

        .submit-disabled-tooltip .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.9rem;
        }

        .submit-disabled-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?><?php echo $paused ? ' paused' : ''; ?>">
    <button class="theme-toggle" id="themeToggle">
        <?php echo $dark_mode ? '☀️' : '🌙'; ?>
    </button>

    <header>
        <h1>数独练习</h1>
        <div class="subtitle">挑战你的逻辑思维，刷新你的最快纪录！</div>
    </header>

    <div class="container">
        <div class="game-section">
            <div class="controls">
                <div class="timer" id="timer"><?php echo $formatted_time; ?></div>
                <div class="button-group">
                    <button id="newGameBtn">新游戏</button>
                    <button id="resetBtn">重置</button>
                    <button id="pauseBtn" class="pause-btn"><?php echo $paused ? '继续' : '暂停'; ?></button>
                    <button id="noteModeBtn" class="note-mode-btn <?php echo $note_mode ? 'active' : ''; ?>">
                        <?php echo $note_mode ? '退出标记' : '标记模式'; ?>
                    </button>
                    <button id="showAnswerBtn">显示答案</button>
                    <?php if ($cheated): ?>
                        <div class="submit-disabled-tooltip">
                            <button id="submitScoreBtn" class="submit-btn" disabled>
                                提交成绩
                            </button>
                            <span class="tooltip-text">您已查看答案，无法提交成绩</span>
                        </div>
                    <?php else: ?>
                        <button id="submitScoreBtn" class="submit-btn">提交成绩</button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($completed): ?>
                <div class="completion-message">恭喜！您已完成数独！</div>
            <?php endif; ?>

            <?php if ($cheated): ?>
                <div class="cheated-warning">您已查看答案，无法提交成绩</div>
            <?php endif; ?>

            <?php if ($note_mode): ?>
                <div class="note-mode-instruction">标记模式已激活 - 点击数字添加/移除候选数</div>
            <?php endif; ?>

            <div class="sudoku-container">
                <div class="sudoku-grid <?php echo $cheated ? 'cheated' : ''; ?><?php echo $note_mode ? ' note-mode-active' : ''; ?>" id="sudokuGrid">
                    <?php for ($i = 0; $i < 9; $i++): ?>
                        <?php for ($j = 0; $j < 9; $j++): ?>
                            <?php 
                            $value = $user_board[$i][$j];
                            $is_fixed = $sudoku['board'][$i][$j] != 0;
                            $cell_class = $is_fixed ? 'cell fixed' : 'cell';
                            if ($note_mode) $cell_class .= ' note-mode-hover';

                            $note_count = count($notes[$i][$j]);
                            ?>
                            <div class="<?php echo $cell_class; ?>" 
                                 data-row="<?php echo $i; ?>" 
                                 data-col="<?php echo $j; ?>">
                                <?php if ($value != 0): ?>
                                    <div class="main-value"><?php echo $value; ?></div>
                                <?php else: ?>
                                    <!-- 优化标记显示 - 简洁版本 -->
                                    <div class="compact-notes">
                                        <?php 
                                        $note_numbers = $notes[$i][$j];
                                        $note_positions = [
                                            [1, 1], [1, 2], [1, 3],
                                            [2, 1], [2, 2], [2, 3],
                                            [3, 1], [3, 2], [3, 3]
                                        ];

                                        // 只显示存在的标记数字
                                        foreach ($note_numbers as $note_num): 
                                            $pos = $note_positions[$note_num - 1];
                                        ?>
                                            <div class="compact-note" style="grid-row: <?php echo $pos[0]; ?>; grid-column: <?php echo $pos[1]; ?>">
                                                <span class="note-value"><?php echo $note_num; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    <?php endfor; ?>
                </div>
                <?php if ($paused): ?>
                    <div class="paused-overlay">已暂停</div>
                <?php endif; ?>
            </div>

            <div class="number-pad" id="numberPad">
                <?php for ($i = 1; $i <= 9; $i++): ?>
                    <div class="number-btn" data-value="<?php echo $i; ?>"><?php echo $i; ?></div>
                <?php endfor; ?>
                <div class="number-btn" id="clearBtn">清除</div>
            </div>
        </div>

        <div class="sidebar">
            <div class="panel">
                <h2>排行榜</h2>
                <ul class="ranking-list">
                    <?php if (empty($ranking)): ?>
                        <li>暂无记录</li>
                    <?php else: ?>
                        <?php foreach ($ranking as $index => $entry): ?>
                            <li class="ranking-item <?php echo strpos($entry['name'], '查看答案') !== false ? 'cheated-entry' : ''; ?>">
                                <span><?php echo $index + 1; ?>. <?php echo htmlspecialchars($entry['name']); ?></span>
                                <span><?php echo gmdate("i:s", $entry['time']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="panel">
                <h2>游戏说明</h2>
                <p><strong>标记模式使用指南：</strong></p>
                <ol>
                    <li>点击"标记模式"按钮进入标记状态</li>
                    <li>选择要标记的单元格</li>
                    <li>点击数字添加或移除候选标记</li>
                    <li>标记的数字会显示在单元格中</li>
                    <li>每个格子可同时标记多个数字</li>
                    <li>点击"退出标记"返回正常模式</li>
                </ol>
                <p>规则：</p>
                <ul>
                    <li>每行、每列和每个3x3宫格必须包含1-9的数字</li>
                    <li>不能有任何重复的数字</li>
                </ul>
                <p>提示：</p>
                <ul>
                    <li>使用标记模式记录候选数字</li>
                    <li>深色模式可减少眼睛疲劳</li>
                    <li>完成数独后记得提交成绩</li>
                    <li>按N键可快速切换标记模式</li>
                    <li><strong>查看答案后将无法提交成绩</strong></li>
                </ul>
            </div>
        </div>
    </div>

    <footer>
        <a href="introduction/about.html">关于本项目</a>
    </footer>

    <div class="modal" id="completionModal">
        <div class="modal-content">
            <h2>恭喜完成!</h2>
            <p>你用时: <span id="finalTime"><?php echo $formatted_time; ?></span></p>
            <?php if ($cheated): ?>
                <div class="cheated-warning">您已查看答案，无法提交成绩</div>
            <?php endif; ?>
            <form method="post" id="submitForm">
                <div class="form-group">
                    <label for="player_name">输入名字记录成绩:</label>
                    <input type="text" id="player_name" name="player_name" maxlength="15" 
                           placeholder="输入你的名字" required>
                </div>
                <div class="modal-buttons">
                    <button type="button" id="cancelSubmit">取消</button>
                    <button type="submit" name="submit" class="submit-btn" <?php echo $cheated ? 'disabled' : ''; ?>>提交成绩</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 当前选中的单元格
        let selectedCell = null;
        let noteMode = <?php echo $note_mode ? 'true' : 'false'; ?>;
        let showAnswerMode = false;
        let timerInterval = null;
        let paused = <?php echo $paused ? 'true' : 'false'; ?>;
        let elapsedSeconds = <?php echo $elapsed_time; ?>;
        let cheated = <?php echo $cheated ? 'true' : 'false'; ?>;

        // 初始化计时器
        function startTimer() {
            if (!paused) {
                timerInterval = setInterval(() => {
                    elapsedSeconds++;
                    const minutes = Math.floor(elapsedSeconds / 60);
                    const secs = elapsedSeconds % 60;
                    document.getElementById('timer').textContent = 
                        `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                }, 1000);
            }
        }

        // 选择单元格
        function selectCell(cell) {
            if (paused) return;

            // 移除之前的高亮
            document.querySelectorAll('.cell.highlighted').forEach(c => {
                c.classList.remove('highlighted');
            });

            // 高亮当前行和列
            const row = cell.dataset.row;
            const col = cell.dataset.col;

            document.querySelectorAll(`.cell[data-row="${row}"]`).forEach(c => {
                c.classList.add('highlighted');
            });

            document.querySelectorAll(`.cell[data-col="${col}"]`).forEach(c => {
                c.classList.add('highlighted');
            });

            // 高亮当前3x3宫格
            const boxRow = Math.floor(row / 3) * 3;
            const boxCol = Math.floor(col / 3) * 3;

            for (let i = boxRow; i < boxRow + 3; i++) {
                for (let j = boxCol; j < boxCol + 3; j++) {
                    const cellElement = document.querySelector(`.cell[data-row="${i}"][data-col="${j}"]`);
                    if (cellElement) {
                        cellElement.classList.add('highlighted');
                    }
                }
            }

            // 高亮当前单元格
            cell.classList.add('highlighted');
            selectedCell = cell;
        }

        // 设置单元格值
        function setCellValue(value) {
            if (paused) return;
            if (!selectedCell || selectedCell.classList.contains('fixed')) return;

            const formData = new FormData();
            formData.append('cell', `${selectedCell.dataset.row},${selectedCell.dataset.col}`);

            if (noteMode) {
                formData.append('note', value);
            } else {
                formData.append('value', value);
            }

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    location.reload();
                }
            });
        }

        // 清除单元格
        function clearCell() {
            if (paused) return;
            if (!selectedCell || selectedCell.classList.contains('fixed')) return;

            const formData = new FormData();
            formData.append('cell', `${selectedCell.dataset.row},${selectedCell.dataset.col}`);
            formData.append('clear', '1');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    location.reload();
                }
            });
        }

        // 切换深色模式
        function toggleDarkMode() {
            const isDarkMode = document.body.classList.toggle('dark-mode');
            document.getElementById('themeToggle').textContent = isDarkMode ? '☀️' : '🌙';
            document.cookie = `dark_mode=${isDarkMode}; path=/; max-age=${60*60*24*365}`;
        }

        // 显示答案
        function showAnswer() {
            const formData = new FormData();
            formData.append('show_answer', '1');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    location.reload();
                }
            });
        }

        // 提交成绩
        function submitScore() {
            // 检查是否已完成
            let completed = true;
            document.querySelectorAll('.cell').forEach(cell => {
                if (!cell.classList.contains('fixed') && !cell.querySelector('.main-value')) {
                    completed = false;
                }
            });

            if (completed) {
                // 检查是否已经作弊
                if (cheated) {
                    alert('您已经查看了答案，无法提交成绩！');
                    return;
                }
                document.getElementById('completionModal').style.display = 'flex';
            } else {
                alert('请先完成数独游戏再提交成绩！');
            }
        }

        // 切换暂停状态
        function togglePause() {
            const formData = new FormData();
            formData.append('toggle_pause', '1');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    location.reload();
                }
            });
        }

        // 切换标记模式
        function toggleNoteMode() {
            const formData = new FormData();
            formData.append('toggle_note_mode', '1');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    location.reload();
                }
            });
        }

        // 事件监听
        document.addEventListener('DOMContentLoaded', () => {
            // 启动计时器
            startTimer();

            // 单元格选择
            document.querySelectorAll('.cell').forEach(cell => {
                cell.addEventListener('click', () => {
                    if (!paused) {
                        selectCell(cell);
                    }
                });
            });

            // 数字按钮
            document.querySelectorAll('.number-btn').forEach(btn => {
                if (btn.id !== 'clearBtn') {
                    btn.addEventListener('click', () => {
                        if (!paused && selectedCell) {
                            setCellValue(btn.dataset.value);
                        } else if (!paused && noteMode) {
                            alert('请先选择一个单元格');
                        }
                    });
                }
            });

            // 清除按钮
            document.getElementById('clearBtn').addEventListener('click', () => {
                if (!paused && selectedCell) {
                    clearCell();
                } else if (!paused) {
                    alert('请先选择一个单元格');
                }
            });

            // 新游戏按钮
            document.getElementById('newGameBtn').addEventListener('click', () => {
                const formData = new FormData();
                formData.append('new_game', '1');

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        location.reload();
                    }
                });
            });

            // 重置按钮
            document.getElementById('resetBtn').addEventListener('click', () => {
                const formData = new FormData();
                formData.append('reset', '1');

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        location.reload();
                    }
                });
            });

            // 暂停按钮
            document.getElementById('pauseBtn').addEventListener('click', togglePause);

            // 标记模式按钮
            document.getElementById('noteModeBtn').addEventListener('click', toggleNoteMode);

            // 显示答案按钮
            document.getElementById('showAnswerBtn').addEventListener('click', showAnswer);

            // 提交成绩按钮
            document.getElementById('submitScoreBtn').addEventListener('click', submitScore);

            // 主题切换
            document.getElementById('themeToggle').addEventListener('click', toggleDarkMode);

            // 键盘输入支持
            document.addEventListener('keydown', (e) => {
                if (paused) return;

                if (e.key >= '1' && e.key <= '9') {
                    if (selectedCell) setCellValue(e.key);
                } else if (e.key === 'Backspace' || e.key === 'Delete' || e.key === ' ') {
                    if (selectedCell) clearCell();
                } else if (e.key === 'n' || e.key === 'N') {
                    toggleNoteMode();
                }
            });

            // 检查游戏是否完成
            <?php if ($completed): ?>
                setTimeout(() => {
                    // 如果查看了答案，不显示提交弹窗
                    if (!cheated) {
                        document.getElementById('completionModal').style.display = 'flex';
                    }
                }, 500);
            <?php endif; ?>

            // 关闭完成弹窗
            document.getElementById('cancelSubmit').addEventListener('click', () => {
                document.getElementById('completionModal').style.display = 'none';
            });
        });
    </script>
</body>
</html>