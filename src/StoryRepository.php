<?php

declare(strict_types=1);

final class StoryRepository
{
    private const STATUSES = ['todo', 'doing', 'done'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function seedIfEmpty(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM stories')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $examples = [
            [
                'title' => 'Kasutajana tahan lisada uue story, et saaksin tööülesande backlogi panna.',
                'description' => 'Uue story loomise vorm lisab töö backlogi.',
                'status' => 'todo',
                'points' => 3,
                'acceptanceCriteria' => [
                    'Vormis saab sisestada pealkirja.',
                    'Vormis saab sisestada kirjelduse.',
                    'Vormis saab sisestada punktid.',
                    'Salvestamisel ilmub story Todo / Backlog veergu.',
                ],
            ],
            [
                'title' => 'Kasutajana tahan muuta story staatust, et näidata töö edenemist.',
                'description' => 'Story liigub staatuse muutmisel õigesse veergu.',
                'status' => 'doing',
                'points' => 5,
                'acceptanceCriteria' => [
                    'Story staatust saab muuta.',
                    'Lubatud staatused on todo, doing ja done.',
                    'Story liigub õige staatuse veergu.',
                ],
            ],
            [
                'title' => 'Kasutajana tahan lisada story juurde kommentaare, et arutelu oleks story juures nähtav.',
                'description' => 'Kommentaarid jäävad story detailvaatesse alles.',
                'status' => 'todo',
                'points' => 3,
                'acceptanceCriteria' => [
                    'Kommentaari saab sisestada.',
                    'Kommentaari saab salvestada.',
                    'Kommentaar kuvatakse story juures.',
                    'Kommentaari juures kuvatakse lisamise aeg.',
                ],
            ],
            [
                'title' => 'Kasutajana tahan backlogi story’sid ümber järjestada, et saaksin määrata prioriteedi.',
                'description' => 'Backlogi järjekord salvestatakse pärast lohistamist.',
                'status' => 'todo',
                'points' => 8,
                'acceptanceCriteria' => [
                    'Backlogis olevaid story’sid saab hiirega lohistada.',
                    'Uus järjekord salvestatakse.',
                    'Pärast lehe uuendamist jääb järjekord samaks.',
                ],
            ],
        ];

        foreach ($examples as $story) {
            $this->create($story);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $rows = $this->pdo->query(
            "SELECT * FROM stories ORDER BY CASE status WHEN 'todo' THEN 0 WHEN 'doing' THEN 1 ELSE 2 END, priority ASC, id ASC"
        )->fetchAll();

        return array_map(fn (array $row): array => $this->hydrate($row), $rows);
    }

    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new ValidationException('Storyt ei leitud.', 404);
        }

        return $this->hydrate($row);
    }

    /** @param array<string, mixed> $input */
    public function create(array $input): array
    {
        $data = $this->validateStoryInput($input);
        $now = $this->now();
        $priority = $data['status'] === 'todo' ? $this->nextPriority() : 0;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO stories (title, description, status, points, priority, created_at, updated_at)
                 VALUES (:title, :description, :status, :points, :priority, :created_at, :updated_at)'
            );
            $stmt->execute([
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => $data['status'],
                'points' => $data['points'],
                'priority' => $priority,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $id = (int) $this->pdo->lastInsertId();
            $this->replaceCriteria($id, $data['acceptanceCriteria']);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->find($id);
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): array
    {
        $this->find($id);
        $data = $this->validateStoryInput($input);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE stories
                 SET title = :title, description = :description, status = :status, points = :points, updated_at = :updated_at
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => $data['status'],
                'points' => $data['points'],
                'updated_at' => $this->now(),
            ]);
            $this->replaceCriteria($id, $data['acceptanceCriteria']);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        $this->find($id);
        $stmt = $this->pdo->prepare('DELETE FROM stories WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function updateStatus(int $id, string $status): array
    {
        $this->find($id);
        $this->assertStatus($status);

        $priority = $status === 'todo' ? $this->nextPriority() : 0;
        $stmt = $this->pdo->prepare(
            'UPDATE stories SET status = :status, priority = :priority, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'priority' => $priority,
            'updated_at' => $this->now(),
        ]);

        return $this->find($id);
    }

    /** @param array<int, array<string, mixed>> $items */
    public function reorder(array $items): array
    {
        if ($items === []) {
            throw new ValidationException('Järjestuse nimekiri ei tohi olla tühi.');
        }

        $this->pdo->beginTransaction();
        try {
            foreach ($items as $index => $item) {
                if (!isset($item['id'], $item['status'])) {
                    throw new ValidationException('Igal järjestuse elemendil peab olema id ja status.');
                }

                $id = $this->asPositiveId($item['id']);
                $status = trim((string) $item['status']);
                $this->assertStatus($status);
                $this->find($id);

                $stmt = $this->pdo->prepare(
                    'UPDATE stories SET status = :status, priority = :priority, updated_at = :updated_at WHERE id = :id'
                );
                $stmt->execute([
                    'id' => $id,
                    'status' => $status,
                    'priority' => $status === 'todo' ? $index + 1 : 0,
                    'updated_at' => $this->now(),
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->all();
    }

    public function addComment(int $storyId, string $text): array
    {
        $this->find($storyId);
        $text = trim($text);
        if ($text === '') {
            throw new ValidationException('Kommentaar ei tohi olla tühi.');
        }

        $stmt = $this->pdo->prepare('INSERT INTO comments (story_id, text, created_at) VALUES (:story_id, :text, :created_at)');
        $stmt->execute([
            'story_id' => $storyId,
            'text' => $text,
            'created_at' => $this->now(),
        ]);

        return $this->find($storyId);
    }

    public function deleteComment(int $storyId, int $commentId): array
    {
        $this->find($storyId);
        $stmt = $this->pdo->prepare('DELETE FROM comments WHERE story_id = :story_id AND id = :id');
        $stmt->execute(['story_id' => $storyId, 'id' => $commentId]);

        if ($stmt->rowCount() === 0) {
            throw new ValidationException('Kommentaari ei leitud.', 404);
        }

        return $this->find($storyId);
    }

    private function hydrate(array $row): array
    {
        $criteriaStmt = $this->pdo->prepare('SELECT text FROM acceptance_criteria WHERE story_id = :story_id ORDER BY position ASC, id ASC');
        $criteriaStmt->execute(['story_id' => $row['id']]);

        $commentsStmt = $this->pdo->prepare('SELECT id, text, created_at AS createdAt FROM comments WHERE story_id = :story_id ORDER BY id ASC');
        $commentsStmt->execute(['story_id' => $row['id']]);

        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'status' => $row['status'],
            'points' => (int) $row['points'],
            'priority' => (int) $row['priority'],
            'acceptanceCriteria' => array_map(fn (array $item): string => $item['text'], $criteriaStmt->fetchAll()),
            'comments' => array_map(fn (array $comment): array => [
                'id' => (int) $comment['id'],
                'text' => $comment['text'],
                'createdAt' => $comment['createdAt'],
            ], $commentsStmt->fetchAll()),
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    /** @param array<string, mixed> $input */
    private function validateStoryInput(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new ValidationException('Pealkiri on kohustuslik.');
        }

        $status = trim((string) ($input['status'] ?? 'todo'));
        $this->assertStatus($status);

        if (!array_key_exists('points', $input) || $input['points'] === '') {
            throw new ValidationException('Punktid on kohustuslikud.');
        }

        if (filter_var($input['points'], FILTER_VALIDATE_INT) === false) {
            throw new ValidationException('Punktid peavad olema täisarv.');
        }

        $points = (int) $input['points'];
        if ($points < 0) {
            throw new ValidationException('Punktid ei tohi olla negatiivsed.');
        }

        $criteria = $input['acceptanceCriteria'] ?? [];
        if (is_string($criteria)) {
            $criteria = preg_split('/\r\n|\r|\n/', $criteria) ?: [];
        }
        if (!is_array($criteria)) {
            throw new ValidationException('Vastuvõtutingimused peavad olema nimekirjana.');
        }

        $criteria = array_values(array_filter(array_map(
            fn (mixed $item): string => trim((string) $item),
            $criteria
        ), fn (string $item): bool => $item !== ''));

        if ($criteria === []) {
            throw new ValidationException('Lisa vähemalt üks vastuvõtutingimus.');
        }

        return [
            'title' => $title,
            'description' => trim((string) ($input['description'] ?? '')),
            'status' => $status,
            'points' => $points,
            'acceptanceCriteria' => $criteria,
        ];
    }

    private function replaceCriteria(int $storyId, array $criteria): void
    {
        $delete = $this->pdo->prepare('DELETE FROM acceptance_criteria WHERE story_id = :story_id');
        $delete->execute(['story_id' => $storyId]);

        $insert = $this->pdo->prepare(
            'INSERT INTO acceptance_criteria (story_id, text, position) VALUES (:story_id, :text, :position)'
        );

        foreach ($criteria as $index => $text) {
            $insert->execute([
                'story_id' => $storyId,
                'text' => $text,
                'position' => $index + 1,
            ]);
        }
    }

    private function assertStatus(string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new ValidationException('Staatus peab olema todo, doing või done.');
        }
    }

    private function nextPriority(): int
    {
        return (int) $this->pdo->query("SELECT COALESCE(MAX(priority), 0) + 1 FROM stories WHERE status = 'todo'")->fetchColumn();
    }

    private function asPositiveId(mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            throw new ValidationException('ID peab olema positiivne täisarv.');
        }

        return (int) $value;
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i');
    }
}

