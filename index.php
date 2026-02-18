<?php
require_once __DIR__ . '/classes/DataStore.php';

$store = new DataStore(__DIR__ . '/data/app-data.json');
$data = $store->load();

$projects = array_map(fn($item) => Project::fromArray($item), $data['projects'] ?? []);
$entries = array_map(fn($item) => TimeEntry::fromArray($item), $data['entries'] ?? []);
$moods = array_map(fn($item) => MoodEntry::fromArray($item), $data['moods'] ?? []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_project') {
        $projects[] = new Project(
            uniqid('project_', true),
            trim((string) ($_POST['project_name'] ?? 'Untitled Project')),
            trim((string) ($_POST['project_description'] ?? '')),
            (string) ($_POST['project_priority'] ?? 'medium')
        );
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
    if ($project->priority === 'high') {
        $messages[] = "⚡ {$project->name} is high priority. Reserve deep-work blocks today.";
    }
}

if (!empty($moods)) {
    $latestMood = $moods[count($moods) - 1];
    $messages[] = "Mood check-in: {$latestMood->mood} — {$latestMood->reflection}";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moodboard + Timesheet Hub</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="overlay" id="overlay"></div>
    <main class="app-shell" id="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="brand-row">
                <button class="burger" id="burger-btn" type="button" aria-label="Toggle menu">☰</button>
                <div>
                    <p class="eyebrow">Productivity Studio</p>
                    <h1>Focus Desk</h1>
                </div>
            </div>

            <nav class="menu stack">
                <button class="menu-item active" data-tab="projects-tab" type="button">Projects</button>
                <button class="menu-item" data-tab="work-tab" type="button">Log Work Session</button>
                <button class="menu-item" data-tab="mood-tab" type="button">Daily Moodboard Entry</button>
                <button class="menu-item" data-tab="add-project-tab" type="button">Add Project</button>
            </nav>

            <p class="glass-pill">Today: <?= htmlspecialchars(date('D, M j Y')) ?></p>
        </aside>

        <section class="right-column">
            <section class="content-panels card" id="tab-container">
                <article class="tab-panel active" id="projects-tab">
                    <h2>All Projects (click to focus)</h2>
                    <div class="list stack">
                        <?php foreach ($projects as $project): ?>
                            <button
                                type="button"
                                class="project-focus-trigger"
                                data-id="<?= htmlspecialchars($project->id) ?>"
                                data-name="<?= htmlspecialchars($project->name) ?>"
                                data-description="<?= htmlspecialchars($project->description) ?>"
                                data-priority="<?= htmlspecialchars($project->priority) ?>"
                            >
                                <strong><?= htmlspecialchars($project->name) ?></strong>
                                <span><?= htmlspecialchars($project->description) ?></span>
                                <small class="priority-pill <?= htmlspecialchars($project->priority) ?>">Priority: <?= htmlspecialchars(ucfirst($project->priority)) ?></small>
                            </button>

                            <form method="post" class="project-item stack">
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
                                <button type="submit">Update</button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="tab-panel" id="work-tab">
                    <h2>Log Work Session (calendar + clock)</h2>
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
                </article>

                <article class="tab-panel" id="mood-tab">
                    <h2>Daily Moodboard Entry</h2>
                    <form method="post" class="stack">
                        <input type="hidden" name="action" value="add_mood">
                        <label>Date <input type="date" name="mood_date" required value="<?= htmlspecialchars(date('Y-m-d')) ?>"></label>
                        <label>Mood
                            <select name="mood_icon">
                                <option>😀</option><option>🙂</option><option>😌</option><option>🔥</option><option>😴</option><option>🤯</option>
                            </select>
                        </label>
                        <label>Reflection <textarea name="mood_reflection" placeholder="What shaped your mood today?"></textarea></label>
                        <button type="submit">Save mood</button>
                    </form>
                </article>

                <article class="tab-panel" id="add-project-tab">
                    <h2>Add Project</h2>
                    <form method="post" class="stack">
                        <input type="hidden" name="action" value="add_project">
                        <label>Name <input name="project_name" required placeholder="New project name"></label>
                        <label>Description <textarea name="project_description" placeholder="What does it do?"></textarea></label>
                        <label>Priority
                            <select name="project_priority">
                                <option value="high">High</option>
                                <option value="medium" selected>Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </label>
                        <button type="submit">Add project</button>
                    </form>
                </article>
            </section>

            <section class="card timesheet-card">
                <h2>Timesheet</h2>
                <div class="list">
                    <?php foreach (array_reverse($entries) as $entry): ?>
                        <?php $projectName = $projectMap[$entry->projectId]->name ?? 'Unknown Project'; ?>
                        <div class="entry-row">
                            <strong><?= htmlspecialchars($projectName) ?></strong>
                            <span><?= htmlspecialchars($entry->date) ?> · <?= htmlspecialchars($entry->startTime) ?>–<?= htmlspecialchars($entry->endTime) ?></span>
                            <span><?= htmlspecialchars((string) $entry->getHours()) ?>h</span>
                            <p><?= htmlspecialchars($entry->note) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card messages-docked">
                <h2>Messages</h2>
                <ul class="message-list">
                    <?php foreach ($messages as $message): ?>
                        <li><?= htmlspecialchars($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </section>
    </main>

    <dialog id="project-modal" class="project-modal">
        <button type="button" class="close-modal" id="close-modal">×</button>
        <h3 id="modal-project-name"></h3>
        <p id="modal-project-description"></p>
        <p id="modal-project-priority" class="priority-pill"></p>
    </dialog>

    <script>
        const menuItems = document.querySelectorAll('.menu-item');
        const panels = document.querySelectorAll('.tab-panel');
        const sidebar = document.getElementById('sidebar');
        const burger = document.getElementById('burger-btn');

        menuItems.forEach((item) => {
            item.addEventListener('click', () => {
                const target = item.getAttribute('data-tab');
                menuItems.forEach((btn) => btn.classList.remove('active'));
                item.classList.add('active');

                panels.forEach((panel) => {
                    panel.classList.toggle('active', panel.id === target);
                });

                if (window.innerWidth < 980) {
                    sidebar.classList.remove('expanded');
                }
            });
        });

        burger.addEventListener('click', () => {
            sidebar.classList.toggle('expanded');
        });

        const modal = document.getElementById('project-modal');
        const overlay = document.getElementById('overlay');
        const modalName = document.getElementById('modal-project-name');
        const modalDesc = document.getElementById('modal-project-description');
        const modalPriority = document.getElementById('modal-project-priority');

        document.querySelectorAll('.project-focus-trigger').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                modalName.textContent = trigger.dataset.name;
                modalDesc.textContent = trigger.dataset.description || 'No description yet.';
                modalPriority.textContent = `Priority: ${trigger.dataset.priority}`;
                modalPriority.className = `priority-pill ${trigger.dataset.priority}`;
                overlay.classList.add('active');
                modal.showModal();
            });
        });

        const closeModal = () => {
            if (modal.open) {
                modal.close();
                overlay.classList.remove('active');
            }
        };

        document.getElementById('close-modal').addEventListener('click', closeModal);
        overlay.addEventListener('click', closeModal);
    </script>
</body>
</html>
