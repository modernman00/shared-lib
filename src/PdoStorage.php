<?php

namespace App\shared;

use Symfony\Component\RateLimiter\Storage\StorageInterface;
use Symfony\Component\RateLimiter\LimiterStateInterface;

class PdoStorage implements StorageInterface
{
  private \PDO $pdo;

  public function __construct(\PDO $pdo)
  {
    $this->pdo = $pdo;
    $this->initializeTable();
  }

  private function initializeTable(): void
  {
    $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rate_limiter (
                id VARCHAR(255) NOT NULL,
                state BLOB NOT NULL,
                PRIMARY KEY(id)
            )
        ");
  }

  /*************  ✨ Windsurf Command ⭐  *************/
  /**
   * Save a LimiterStateInterface instance to the storage.
   *
   * Will insert a new row if the LimiterStateInterface instance does not exist
   * in the storage, or update the existing row if it already exists.
   *
   * @param LimiterStateInterface $limiterState The LimiterStateInterface to save.
   */
  /*******  5d6ab8dc-cd5f-4523-b8d8-472e6db3b6fb  *******/
  public function save(LimiterStateInterface $limiterState): void
  {
    $stmt = $this->pdo->prepare("
            INSERT INTO rate_limiter (id, state)
            VALUES (:id, :state)
            ON DUPLICATE KEY UPDATE state = :state_update
        ");

    $serializedState = serialize($limiterState);
    $stmt->execute(params: [
      'id' => $limiterState->getId(),
      'state' => $serializedState,
      'state_update' => $serializedState,
    ]);
  }

  public function fetch(string $limiterStateId): ?LimiterStateInterface
  {
    $stmt = $this->pdo->prepare("SELECT state FROM rate_limiter WHERE id = :id");
    $stmt->execute(['id' => $limiterStateId]);
    $data = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($data === false) {
      return null;
    }

    return unserialize($data['state']);
  }

  public function delete(string $limiterStateId): void
  {
    $stmt = $this->pdo->prepare("DELETE FROM rate_limiter WHERE id = :id");
    $stmt->execute(['id' => $limiterStateId]);
  }
}
