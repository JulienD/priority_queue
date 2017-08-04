<?php

namespace Drupal\priority_queue\queue;

use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\DatabaseQueue;

/**
 * Default queue implementation.
 *
 * @ingroup queue
 */
class PriorityQueue extends DatabaseQueue {

  /**
   * The database table name.
   */
  const TABLE_NAME = 'queue_priority';

  /**
   * The name of the queue this instance is working with.
   *
   * @var string
   */
  protected $name;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection $connection
   */
  protected $connection;

  /**
   * Constructs a \Drupal\priority_queue\Queue\PriorityQueue object.
   *
   * @param string $name
   *   The name of the queue.
   * @param \Drupal\Core\Database\Connection $connection
   *   The Connection object containing the key-value tables.
   */
  public function __construct($name, Connection $connection) {
    parent::__construct($name, $connection);
  }

  /**
   * Adds a queue item and store it directly to the queue.
   *
   * @param $data
   *   Arbitrary data to be associated with the new task in the queue.
   * @param int $priority
   *   The associated priority.
   *
   * @return int|FALSE
   *   A unique ID if the item was successfully created and was (best effort)
   *   added to the queue, otherwise FALSE. We don't guarantee the item was
   *   committed to disk etc, but as far as we know, the item is now in the
   *   queue.
   * @throws \Exception
   */
  public function createItem($data, $priority = 0) {
    $try_again = FALSE;
    try {
      $id = $this->doCreateItem($data, $priority);
    }
    catch (\Exception $e) {
      // If there was an exception, try to create the table.
      if (!$try_again = $this->ensureTableExists()) {
        // If the exception happened for other reason than the missing table,
        // propagate the exception.
        throw $e;
      }
    }
    // Now that the table has been created, try again if necessary.
    if ($try_again) {
      $id = $this->doCreateItem($data, $priority);
    }
    return $id;
  }

  /**
   * Adds a queue item and store it directly to the queue.
   *
   * @param $data
   *   Arbitrary data to be associated with the new task in the queue.
   * @param int $priority
   *   The associated priority.
   *
   * @return int|FALSE
   *   A unique ID if the item was successfully created and was (best effort)
   *   added to the queue, otherwise FALSE. We don't guarantee the item was
   *   committed to disk etc, but as far as we know, the item is now in the
   *   queue.
   */
  protected function doCreateItem($data, $priority) {
    $query = $this->connection->insert(static::TABLE_NAME)
      ->fields([
        'name' => $this->name,
        'data' => serialize($data),
        'priority' => $priority,
        // We cannot rely on REQUEST_TIME because many items might be created
        // by a single request which takes longer than 1 second.
        'created' => time(),
      ]);
    // Return the new serial ID, or FALSE on failure.
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function claimItem($lease_time = 30) {
    // Claim an item by updating its expire fields. If claim is not successful
    // another thread may have claimed the item in the meantime. Therefore loop
    // until an item is successfully claimed or we are reasonably sure there
    // are no unclaimed items left.
    while (TRUE) {
      try {
        $item = $this->connection->queryRange('SELECT data, priority, created, item_id FROM {' . static::TABLE_NAME . '} q WHERE expire = 0 AND name = :name ORDER BY priority, created, item_id ASC', 0, 1, [':name' => $this->name])->fetchObject();
      }
      catch (\Exception $e) {
        $this->catchException($e);
        // If the table does not exist there are no items currently available to
        // claim.
        return FALSE;
      }
      if ($item) {
        // Try to update the item. Only one thread can succeed in UPDATEing the
        // same row. We cannot rely on REQUEST_TIME because items might be
        // claimed by a single consumer which runs longer than 1 second. If we
        // continue to use REQUEST_TIME instead of the current time(), we steal
        // time from the lease, and will tend to reset items before the lease
        // should really expire.
        $update = $this->connection->update(static::TABLE_NAME)
          ->fields([
            'expire' => time() + $lease_time,
          ])
          ->condition('item_id', $item->item_id)
          ->condition('expire', 0);
        // If there are affected rows, this update succeeded.
        if ($update->execute()) {
          $item->data = unserialize($item->data);
          return $item;
        }
      }
      else {
        // No items currently available to claim.
        return FALSE;
      }
    }
  }

  /**
   * Defines the schema for the queue table.
   */
  public function schemaDefinition() {
    return [
      'description' => 'Stores items in queues.',
      'fields' => [
        'item_id' => [
          'type' => 'serial',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'description' => 'Primary Key: Unique item ID.',
        ],
        'name' => [
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
          'description' => 'The queue name.',
        ],
        'data' => [
          'type' => 'blob',
          'not null' => FALSE,
          'size' => 'big',
          'serialize' => TRUE,
          'description' => 'The arbitrary data for the item.',
        ],
        'priority' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'The priority of the queued item. Items with higher priorities will be processed first.',
        ],
        'expire' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'Timestamp when the claim lease expires on the item.',
        ],
        'created' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'Timestamp when the item was created.',
        ],
      ],
      'primary key' => ['item_id'],
      'indexes' => [
        'name_created' => ['name', 'created'],
        'name_priority' => ['name', 'priority', 'created'],
        'expire' => ['expire'],
      ],
    ];
  }

}
