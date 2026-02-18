<?php
require_once __DIR__ . '/classes/DataStore.php';

$store = new DataStore(__DIR__ . '/data/app-data.json');
$data = $store->load();

$projects = array_map(fn($item) => Project::fromArray($item), $data['projects'] ?? []);
$entries = array_map(fn($item) => TimeEntry::fromArray($item), $data['entries'] ?? []);
$moods = array_map(fn($item) => MoodEntry::fromArray($item), $data['moods'] ?? []);

$priorityRank = ['high' => 0, 'medium' => 1, 'low' => 2];
usort($projects, static function (Project $a, Project $b) use ($priorityRank): int {
    $rankA = $priorityRank[$a->priority] ?? 3;
    $rankB = $priorityRank[$b->priority] ?? 3;
    return $rankA <=> $rankB;
});

$projectFilesRoot = __DIR__ . '/data/projects';
$ensureProjectFolder = static function (string $projectId) use ($projectFilesRoot): string {
    $folder = $projectFilesRoot . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $projectId);
    if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
        throw new RuntimeException("Unable to create project folder: {$folder}");
    }
    return $folder;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_project') {
        $newProject = new Project(
            uniqid('project_', true),
            trim((string) ($_POST['project_name'] ?? 'Untitled Project')),
            trim((string) ($_POST['project_description'] ?? '')),
            (string) ($_POST['project_priority'] ?? 'medium')
        );
        $projects[] = $newProject;
        $ensureProjectFolder($newProject->id);
    }

    if ($action === 'add_entry') {
        $entries[] = new TimeEntry(
            (string) ($_POST['entry_project'] ?? ''),
            (string) ($_POST['entry_date'] ?? date('Y-m-d')),
            (string) ($_POST['entry_start'] ?? '09:00'),
            (string) ($_POST['entry_end'] ?? '10:00'),
            trim((string) ($_POST['entry_note'] ?? ''))
        );
    }

    if ($action === 'add_mood') {
        $moods[] = new MoodEntry(
            (string) ($_POST['mood_date'] ?? date('Y-m-d')),
            (string) ($_POST['mood_time'] ?? date('H:i')),
            (string) ($_POST['mood_icon'] ?? '🙂'),
            trim((string) ($_POST['mood_reflection'] ?? ''))
        );
    }

    if ($action === 'update_project') {
        $targetId = (string) ($_POST['target_id'] ?? '');
        foreach ($projects as $project) {
            if ($project->id === $targetId) {
                $project->name = trim((string) ($_POST['project_name'] ?? $project->name));
                $project->description = trim((string) ($_POST['project_description'] ?? $project->description));
                $project->priority = (string) ($_POST['project_priority'] ?? $project->priority);
            }
        }
    }

    if ($action === 'add_project_file') {
        $targetId = (string) ($_POST['target_id'] ?? '');
        if (isset($_FILES['project_file']) && is_array($_FILES['project_file']) && ($_FILES['project_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmpName = (string) ($_FILES['project_file']['tmp_name'] ?? '');
            $originalName = basename((string) ($_FILES['project_file']['name'] ?? 'upload.bin'));
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName) ?: 'upload.bin';
            $projectDir = $ensureProjectFolder($targetId);
            $relative = 'data/projects/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $targetId) . '/' . time() . '_' . $safeName;
            $destination = $projectDir . '/' . time() . '_' . $safeName;

            if (!move_uploaded_file($tmpName, $destination)) {
                throw new RuntimeException('Unable to move uploaded file.');
            }

            foreach ($projects as $project) {
                if ($project->id === $targetId) {
                    $project->attachments[] = [
                        'name' => $originalName,
                        'path' => $relative,
                        'uploadedAt' => date('Y-m-d H:i:s'),
                    ];
                }
            }
        }
    }

    $store->save([
        'projects' => array_map(fn(Project $p) => $p->toArray(), $projects),
        'moods' => array_map(fn(MoodEntry $m) => $m->toArray(), $moods),
        'entries' => array_map(fn(TimeEntry $e) => $e->toArray(), $entries),
    ]);

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$projectMap = [];
foreach ($projects as $project) {
    $projectMap[$project->id] = $project;
}

$messages = [];
foreach ($projects as $project) {
    $messages[] = strtoupper($project->priority) . ": {$project->name}";
}
if (!empty($moods)) {
    $latestMood = $moods[count($moods) - 1];
    $messages[] = "Mood check-in: {$latestMood->mood} ({$latestMood->date} {$latestMood->time})";
}

$scoreMap = ['😂' => 6, '🙂' => 5, '😐' => 4, '😴' => 3, '😢' => 2, '🤯' => 1];
$currentMonth = date('Y-m');
$monthMoods = array_values(array_filter($moods, static fn(MoodEntry $m) => str_starts_with($m->date, $currentMonth)));
$chartPoints = [];
$total = count($monthMoods);
foreach ($monthMoods as $idx => $mood) {
    $score = $scoreMap[$mood->mood] ?? 4;
    $x = $total > 1 ? (30 + (($idx * 320) / ($total - 1))) : 190;
    $y = 190 - ($score * 22);
    $chartPoints[] = [
        'x' => $x,
        'y' => $y,
        'mood' => $mood->mood,
        'date' => $mood->date,
        'time' => $mood->time,
        'label' => date('m/d H:i', strtotime($mood->date . ' ' . $mood->time)),
        'reflection' => $mood->reflection,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Focus Desk</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body data-theme="default">
<div class="start-screen" id="start-screen">
    <h1>Focus Desk</h1>
    <button id="lets-go" type="button">let's go!</button>
</div>

<div class="overlay" id="overlay"></div>
<main class="app-shell hidden" id="app-shell">
    <aside class="sidebar" id="sidebar">
        <nav class="menu stack">
            <button class="menu-item active" data-index="0" data-tab="projects-tab" type="button">Projects</button>
            <button class="menu-item" data-index="1" data-tab="work-tab" type="button">Log Work Session</button>
            <button class="menu-item" data-index="2" data-tab="mood-tab" type="button">Daily Moodboard Entry</button>
            <button class="menu-item" data-index="3" data-tab="add-project-tab" type="button">Add Project</button>
            <button class="menu-item" data-index="4" data-tab="settings-tab" type="button">Settings</button>
        </nav>
        <p class="glass-pill">Today: <?= htmlspecialchars(date('D, M j Y')) ?></p>
    </aside>

    <section class="right-column">
        <header class="topbar">
            <h2>Focus Desk</h2>
            <button class="burger" id="burger-btn" type="button" aria-label="Toggle menu">☰</button>
        </header>

        <section class="content-panels card" id="tab-container">
            <article class="tab-panel active" id="projects-tab" data-index="0">
                <h3>Projects (High → Low)</h3>
                <div class="stack">
                    <?php foreach ($projects as $project): ?>
                        <button type="button" class="project-collapsed priority-<?= htmlspecialchars($project->priority) ?>" data-project-id="<?= htmlspecialchars($project->id) ?>">
                            <?= htmlspecialchars($project->name) ?>
                        </button>

                        <template id="project-template-<?= htmlspecialchars($project->id) ?>">
                            <h3><?= htmlspecialchars($project->name) ?></h3>
                            <p class="focus-priority priority-<?= htmlspecialchars($project->priority) ?>">Priority: <?= htmlspecialchars(ucfirst($project->priority)) ?></p>
                            <form method="post" class="stack">
                                <input type="hidden" name="action" value="update_project">
                                <input type="hidden" name="target_id" value="<?= htmlspecialchars($project->id) ?>">
                                <label>Name <input name="project_name" value="<?= htmlspecialchars($project->name) ?>"></label>
                                <label>Description <textarea name="project_description"><?= htmlspecialchars($project->description) ?></textarea></label>
                                <label>Priority
                                    <select name="project_priority">
                                        <option value="high" <?= $project->priority === 'high' ? 'selected' : '' ?>>High</option>
                                        <option value="medium" <?= $project->priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                                        <option value="low" <?= $project->priority === 'low' ? 'selected' : '' ?>>Low</option>
                                    </select>
                                </label>
                                <button type="submit">Update project</button>
                            </form>

                            <h4>Files</h4>
                            <ul class="file-list">
                                <?php foreach ($project->attachments as $file): ?>
                                    <li><a href="<?= htmlspecialchars((string) ($file['path'] ?? '')) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) ($file['name'] ?? 'Attachment')) ?></a></li>
                                <?php endforeach; ?>
                                <?php if (empty($project->attachments)): ?><li><small>No files yet.</small></li><?php endif; ?>
                            </ul>

                            <form method="post" enctype="multipart/form-data" class="stack">
                                <input type="hidden" name="action" value="add_project_file">
                                <input type="hidden" name="target_id" value="<?= htmlspecialchars($project->id) ?>">
                                <label>Add file <input type="file" name="project_file" required></label>
                                <button type="submit">Upload file</button>
                            </form>
                        </template>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="tab-panel" id="work-tab" data-index="1">
                <h3>Log Work Session</h3>
                <form method="post" class="row-form">
                    <input type="hidden" name="action" value="add_entry">
                    <label>Project
                        <select name="entry_project" required>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= htmlspecialchars($project->id) ?>"><?= htmlspecialchars($project->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Date <input type="date" name="entry_date" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required></label>
                    <label>Start <input type="time" name="entry_start" required value="09:00"></label>
                    <label>End <input type="time" name="entry_end" required value="10:00"></label>
                    <label>Note <input name="entry_note" placeholder="Session note"></label>
                    <button type="submit">Log time</button>
                </form>
                <h4>Timesheet</h4>
                <?php foreach (array_reverse($entries) as $entry): ?>
                    <?php $projectName = $projectMap[$entry->projectId]->name ?? 'Unknown Project'; ?>
                    <div class="entry-row">
                        <strong><?= htmlspecialchars($projectName) ?></strong>
                        <span><?= htmlspecialchars($entry->date) ?> · <?= htmlspecialchars($entry->startTime) ?>–<?= htmlspecialchars($entry->endTime) ?></span>
                        <span><?= htmlspecialchars((string) $entry->getHours()) ?>h</span>
                        <p><?= htmlspecialchars($entry->note) ?></p>
                    </div>
                <?php endforeach; ?>
            </article>

            <article class="tab-panel" id="mood-tab" data-index="2">
                <h3>Daily Moodboard Entry</h3>
                <form method="post" class="stack" id="mood-form">
                    <input type="hidden" name="action" value="add_mood">
                    <label>Date <input type="date" name="mood_date" required value="<?= htmlspecialchars(date('Y-m-d')) ?>"></label>
                    <label>Time <input type="time" name="mood_time" required value="<?= htmlspecialchars(date('H:i')) ?>"></label>
                    <label>Mood
                        <select name="mood_icon" id="mood-select">
                            <option>😂</option><option>🙂</option><option>😐</option><option>😴</option><option>😢</option><option>🤯</option>
                        </select>
                    </label>
                    <label>Reflection <textarea name="mood_reflection" placeholder="What shaped your mood today?"></textarea></label>
                    <button type="submit">Save mood</button>
                </form>

                <h4>Monthly mood graph</h4>
                <div class="mood-graph-wrap" id="mood-graph-wrap">
                    <svg class="mood-graph" viewBox="0 0 380 240" role="img" aria-label="Mood trend graph">
                        <polyline points="<?= htmlspecialchars(implode(' ', array_map(static fn($p) => $p['x'] . ',' . $p['y'], $chartPoints))) ?>" fill="none" stroke="var(--accent)" stroke-width="3"></polyline>
                        <?php foreach ($chartPoints as $point): ?>
                            <circle cx="<?= htmlspecialchars((string) $point['x']) ?>" cy="<?= htmlspecialchars((string) $point['y']) ?>" r="5" class="mood-point" data-date="<?= htmlspecialchars($point['date']) ?>" data-time="<?= htmlspecialchars($point['time']) ?>" data-mood="<?= htmlspecialchars($point['mood']) ?>" data-reflection="<?= htmlspecialchars($point['reflection']) ?>"></circle>
                            <text x="<?= htmlspecialchars((string) $point['x']) ?>" y="228" text-anchor="middle" class="graph-label"><?= htmlspecialchars($point['label']) ?></text>
                        <?php endforeach; ?>
                    </svg>
                </div>
            </article>

            <article class="tab-panel" id="add-project-tab" data-index="3">
                <h3>Add Project</h3>
                <form method="post" class="stack">
                    <input type="hidden" name="action" value="add_project">
                    <label>Name <input name="project_name" required placeholder="New project name"></label>
                    <label>Description <textarea name="project_description" placeholder="What does it do?"></textarea></label>
                    <label>Priority
                        <select name="project_priority"><option value="high">High</option><option value="medium" selected>Medium</option><option value="low">Low</option></select>
                    </label>
                    <button type="submit">Add project</button>
                </form>
            </article>

            <article class="tab-panel" id="settings-tab" data-index="4">
                <h3>Settings · Color scheme</h3>
                <div class="scheme-grid">
                    <button type="button" class="scheme-btn" data-theme="default">Default</button>
                    <button type="button" class="scheme-btn" data-theme="sunset">Sunset</button>
                    <button type="button" class="scheme-btn" data-theme="forest">Forest</button>
                </div>
            </article>
        </section>
    </section>
</main>

<button type="button" class="messages-toggle" id="messages-toggle">Messages</button>
<section class="messages-drawer" id="messages-drawer">
    <h4>Messages</h4>
    <ul class="message-list">
        <?php foreach ($messages as $message): ?><li><?= htmlspecialchars($message) ?></li><?php endforeach; ?>
    </ul>
</section>

<dialog id="project-modal" class="project-modal">
    <button type="button" class="close-modal" id="close-project-modal">×</button>
    <div id="project-modal-content"></div>
</dialog>

<dialog id="mood-modal" class="project-modal">
    <button type="button" class="close-modal" id="close-mood-modal">×</button>
    <h3 id="mood-modal-date"></h3>
    <p id="mood-modal-mood"></p>
    <p id="mood-modal-reflection"></p>
</dialog>

<script>
const appShell = document.getElementById('app-shell');
const startScreen = document.getElementById('start-screen');
const sidebar = document.getElementById('sidebar');
const menuItems = [...document.querySelectorAll('.menu-item')];
const panels = [...document.querySelectorAll('.tab-panel')];
let currentIndex = 0;

document.getElementById('lets-go').addEventListener('click', () => {
    startScreen.classList.add('hide');
    appShell.classList.remove('hidden');
    sidebar.classList.add('expanded');
});

document.getElementById('burger-btn').addEventListener('click', () => {
    sidebar.classList.toggle('expanded');
    sidebar.classList.toggle('collapsed');
});

menuItems.forEach((item) => {
    item.addEventListener('click', () => {
        const target = item.dataset.tab;
        const nextIndex = Number(item.dataset.index || 0);
        const direction = nextIndex > currentIndex ? 'down' : 'up';
        currentIndex = nextIndex;

        menuItems.forEach((btn) => btn.classList.remove('active'));
        item.classList.add('active');

        panels.forEach((panel) => {
            if (panel.id === target) {
                panel.classList.remove('active', 'anim-up', 'anim-down');
                panel.classList.add('active', direction === 'down' ? 'anim-down' : 'anim-up');
            } else {
                panel.classList.remove('active', 'anim-up', 'anim-down');
            }
        });
    });
});

const overlay = document.getElementById('overlay');
const projectModal = document.getElementById('project-modal');
const moodModal = document.getElementById('mood-modal');
const closeAllModals = () => {
    if (projectModal.open) projectModal.close();
    if (moodModal.open) moodModal.close();
    overlay.classList.remove('active');
};
document.getElementById('close-project-modal').addEventListener('click', closeAllModals);
document.getElementById('close-mood-modal').addEventListener('click', closeAllModals);
overlay.addEventListener('click', closeAllModals);

document.querySelectorAll('.project-collapsed').forEach((button) => {
    button.addEventListener('click', () => {
        const template = document.getElementById(`project-template-${button.dataset.projectId}`);
        if (!template) return;
        document.getElementById('project-modal-content').innerHTML = template.innerHTML;
        overlay.classList.add('active');
        projectModal.showModal();
    });
});

const openMoodDetails = (source) => {
    document.getElementById('mood-modal-date').textContent = `${source.dataset.date || ''} ${source.dataset.time || ''}`;
    document.getElementById('mood-modal-mood').textContent = `Mood: ${source.dataset.mood || ''}`;
    document.getElementById('mood-modal-reflection').textContent = source.dataset.reflection || '';
    overlay.classList.add('active');
    moodModal.showModal();
};
document.querySelectorAll('.mood-point').forEach((item) => item.addEventListener('click', () => openMoodDetails(item)));

const messagesToggle = document.getElementById('messages-toggle');
const messagesDrawer = document.getElementById('messages-drawer');
messagesToggle.addEventListener('click', () => messagesDrawer.classList.toggle('open'));

const schemeButtons = document.querySelectorAll('.scheme-btn');
schemeButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
        document.body.setAttribute('data-theme', btn.dataset.theme);
        localStorage.setItem('focus-desk-theme', btn.dataset.theme || 'default');
    });
});
const savedTheme = localStorage.getItem('focus-desk-theme');
if (savedTheme) {
    document.body.setAttribute('data-theme', savedTheme);
}

const moodForm = document.getElementById('mood-form');
moodForm.addEventListener('submit', (event) => {
    event.preventDefault();
    const emoji = document.createElement('div');
    emoji.className = 'emoji-shoot';
    emoji.textContent = document.getElementById('mood-select').value;
    document.body.appendChild(emoji);
    requestAnimationFrame(() => emoji.classList.add('run'));
    setTimeout(() => moodForm.submit(), 420);
});
</script>
</body>
</html>
