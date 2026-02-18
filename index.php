<?php

declare(strict_types=1);

require_once __DIR__ . '/classes/DataStore.php';

$store = new DataStore(__DIR__ . '/data/app-data.json');
$data = $store->all();
$projects = $data['projects'];
$timeEntries = $data['timeEntries'];
$moodEntries = $data['moodEntries'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_project') {
        $nextId = empty($projects) ? 1 : max(array_map(static fn(Project $project) => $project->id, $projects)) + 1;
        $projects[] = new Project(
            $nextId,
            trim((string) ($_POST['name'] ?? 'Untitled Project')),
            trim((string) ($_POST['description'] ?? '')),
            (string) ($_POST['priority'] ?? 'medium')
        );
    }

    if ($action === 'add_time_entry') {
        $timeEntries[] = new TimeEntry(
            (int) ($_POST['project_id'] ?? 0),
            (string) ($_POST['date'] ?? date('Y-m-d')),
            (string) ($_POST['start_time'] ?? '09:00'),
            (string) ($_POST['end_time'] ?? '10:00'),
            trim((string) ($_POST['notes'] ?? ''))
        );
    }

    if ($action === 'add_mood') {
        $moodEntries[] = new MoodEntry(
            (string) ($_POST['date'] ?? date('Y-m-d')),
            (string) ($_POST['mood'] ?? 'Neutral'),
            trim((string) ($_POST['details'] ?? ''))
        );
    }

    $store->save($projects, $timeEntries, $moodEntries);
    header('Location: /');
    exit;
}

$projectMap = [];
foreach ($projects as $project) {
    $projectMap[$project->id] = $project;
}

usort($timeEntries, static fn(TimeEntry $a, TimeEntry $b) => strcmp($b->date . $b->startTime, $a->date . $a->startTime));
usort($moodEntries, static fn(MoodEntry $a, MoodEntry $b) => strcmp($b->date, $a->date));

$messages = [];
foreach ($projects as $project) {
    if ($project->priority === 'high') {
        $messages[] = "⚡ {$project->name} is high-priority. Consider blocking focused time today.";
    }
}
if (empty($messages)) {
    $messages[] = 'No urgent projects right now. Keep steady momentum.';
}

function priorityClass(string $priority): string
{
    return match ($priority) {
        'high' => 'priority-high',
        'low' => 'priority-low',
        default => 'priority-medium',
    };
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moodboard & Timesheet</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <h1>Flowboard</h1>
        <p class="subtitle">Mood + project tracker</p>

        <section class="card">
            <h2>Add Project</h2>
            <form method="post" class="stack">
                <input type="hidden" name="action" value="add_project">
                <input name="name" placeholder="Project name" required>
                <textarea name="description" placeholder="Describe what it does" required></textarea>
                <select name="priority">
                    <option value="high">High priority</option>
                    <option value="medium" selected>Medium priority</option>
                    <option value="low">Low priority</option>
                </select>
                <button type="submit">Add project</button>
            </form>
        </section>

        <section class="card">
            <h2>Daily Mood Entry</h2>
            <form method="post" class="stack">
                <input type="hidden" name="action" value="add_mood">
                <input type="date" name="date" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
                <input name="mood" placeholder="Mood (e.g. focused)" required>
                <textarea name="details" placeholder="What shaped today?"></textarea>
                <button type="submit">Save mood</button>
            </form>
        </section>
    </aside>

    <main class="content">
        <section class="hero card">
            <h2>Priority Messages</h2>
            <ul class="messages">
                <?php foreach ($messages as $message): ?>
                    <li><?= htmlspecialchars($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="grid-2">
            <section class="card">
                <h2>Projects</h2>
                <div class="project-list">
                    <?php foreach ($projects as $project): ?>
                        <article class="project-item">
                            <div class="top-row">
                                <h3><?= htmlspecialchars($project->name) ?></h3>
                                <span class="priority-pill <?= priorityClass($project->priority) ?>"><?= htmlspecialchars(strtoupper($project->priority)) ?></span>
                            </div>
                            <p><?= htmlspecialchars($project->description) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card">
                <h2>Log Time (Calendar + Clock View)</h2>
                <form method="post" class="stack inline-grid">
                    <input type="hidden" name="action" value="add_time_entry">
                    <select name="project_id" required>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= $project->id ?>"><?= htmlspecialchars($project->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
                    <input type="time" name="start_time" value="09:00" required>
                    <input type="time" name="end_time" value="10:00" required>
                    <textarea name="notes" placeholder="Session notes"></textarea>
                    <button type="submit">Add timesheet entry</button>
                </form>

                <div class="timeline">
                    <?php foreach ($timeEntries as $entry):
                        $project = $projectMap[$entry->projectId] ?? null;
                        ?>
                        <article class="time-item">
                            <div>
                                <strong><?= htmlspecialchars($project?->name ?? 'Unknown Project') ?></strong>
                                <p><?= htmlspecialchars($entry->notes) ?></p>
                            </div>
                            <div class="time-badges">
                                <span>📅 <?= htmlspecialchars($entry->date) ?></span>
                                <span>🕒 <?= htmlspecialchars($entry->startTime) ?> - <?= htmlspecialchars($entry->endTime) ?></span>
                                <span>⏱ <?= $entry->durationMinutes() ?> min</span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </section>

        <section class="card">
            <h2>Moodboard Timeline</h2>
            <div class="mood-list">
                <?php foreach ($moodEntries as $mood): ?>
                    <article class="mood-item">
                        <h3><?= htmlspecialchars($mood->mood) ?></h3>
                        <small><?= htmlspecialchars($mood->date) ?></small>
                        <p><?= htmlspecialchars($mood->details) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>
