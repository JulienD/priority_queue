<?php

namespace Drupal\Tests\priority_queue\Kernel;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drupal\priority_queue\queue\PriorityQueue;

/**
 * Class PriorityQueueKernelTest.
 *
 * Queues and dequeues a set of items to check the basic queue functionality.
 *
 * @group priority_queue
 */
class PriorityQueueKernelTest extends KernelTestBase {

  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests the System queue.
   */
  public function testSystemQueue() {
    // Create two queues.
    $queue1 = new PriorityQueue($this->randomMachineName(), Database::getConnection());
    $queue1->createQueue();
    $queue2 = new PriorityQueue($this->randomMachineName(), Database::getConnection());
    $queue2->createQueue();

    $this->queueTest($queue1, $queue2);
  }

  /**
   * Queues and dequeues a set of items to check the basic queue functionality.
   *
   * @param \Drupal\Core\Queue\QueueInterface $queue1
   *   An instantiated queue object.
   * @param \Drupal\Core\Queue\QueueInterface $queue2
   *   An instantiated queue object.
   */
  protected function queueTest($queue1, $queue2) {

    // Create three items.
    $data = [
      'LAST',
      'FIRST',
      'MIDDLE',
    ];

    // Queue 2 items in the queue1.
    $queue1->createItem($data['0'], -10);
    $queue1->createItem($data['1'], 10);

    // Retrieve two items from queue1.
    $items = [];
    $new_items = [];

    $items[] = $item = $queue1->claimItem();
    $new_items[] = $item->data;

    // Ensure the first item retrieved has the highest priority.
    $this->assertEqual($item->data, 'FIRST', 'Highest item retrieved first');

    // Checks the item priority.
    $this->assertEqual($item->priority, 10, 'Item priority matched');

    $items[] = $item = $queue1->claimItem();
    $new_items[] = $item->data;

    // First two dequeued items should match the first two items we queued.
    $this->assertEqual($this->queueScore($data, $new_items), 2, 'Two items matched');

    // Add one more items.
    $queue1->createItem($data['2']);

    $this->assertTrue($queue1->numberOfItems(), 'Queue 1 is not empty after adding items.');
    $this->assertFalse($queue2->numberOfItems(), 'Queue 2 is empty while Queue 1 has items');

    $items[] = $item = $queue1->claimItem();
    $new_items[] = $item->data;

    // All dequeued items should match the items we queued exactly once,
    // therefore the score must be exactly 4.
    $this->assertEqual($this->queueScore($data, $new_items), 3, 'Three items matched');

    // Delete all items from queue1.
    foreach ($items as $item) {
      $queue1->deleteItem($item);
    }

    // Check that both queues are empty.
    $this->assertFalse($queue1->numberOfItems(), 'Queue 1 is empty');
    $this->assertFalse($queue2->numberOfItems(), 'Queue 2 is empty');
  }

  /**
   * Returns the number of equal items in two arrays.
   */
  protected function queueScore($items, $new_items) {
    $score = 0;
    foreach ($items as $item) {
      foreach ($new_items as $new_item) {
        if ($item === $new_item) {
          $score++;
        }
      }
    }
    return $score;
  }

}
