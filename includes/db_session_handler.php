<?php

/**
 * Stores PHP sessions in the shared database instead of local disk.
 *
 * This host runs multiple stateless app instances behind a load balancer
 * with no shared filesystem, so PHP's default file-based session storage
 * only persists on whichever instance handled the first request — any
 * follow-up request landing on a different instance sees an empty session.
 * Storing sessions in the (shared) database fixes that regardless of which
 * instance handles a given request.
 */
final class DbSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $maxLifetime;

    public function __construct(PDO $pdo, int $maxLifetime = 1440)
    {
        $this->pdo = $pdo;
        $this->maxLifetime = $maxLifetime;
    }

    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string
    {
        $stmt = $this->pdo->prepare('SELECT data FROM app_sessions WHERE id = :id AND last_activity > :cutoff');
        $stmt->execute(['id' => $id, 'cutoff' => time() - $this->maxLifetime]);
        $row = $stmt->fetch();
        return $row ? $row['data'] : '';
    }

    public function write($id, $data): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO app_sessions (id, data, last_activity) VALUES (:id, :data, :t)
             ON DUPLICATE KEY UPDATE data = :data2, last_activity = :t2'
        );
        return $stmt->execute([
            'id' => $id, 'data' => $data, 't' => time(), 'data2' => $data, 't2' => time(),
        ]);
    }

    public function destroy($id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM app_sessions WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function gc($max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM app_sessions WHERE last_activity < :cutoff');
        $stmt->execute(['cutoff' => time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}
