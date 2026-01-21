<?php

class DatabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;

    public function __construct() {
        // We assume Database class is available and autoloaded or included
        $this->pdo = Database::connect();
    }

    public function open($savePath, $sessionName): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read($id): string|false {
        $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return $result['data'];
        }

        return '';
    }

    public function write($id, $data): bool {
        $access = time();
        $stmt = $this->pdo->prepare("REPLACE INTO sessions (id, access, data) VALUES (:id, :access, :data)");
        return $stmt->execute([
            ':id' => $id,
            ':access' => $access,
            ':data' => $data
        ]);
    }

    public function destroy($id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function gc($max_lifetime): int|false {
        $old = time() - $max_lifetime;
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE access < :old");
        $stmt->execute([':old' => $old]);
        return $stmt->rowCount();
    }
}
