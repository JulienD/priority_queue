# Priority Queue
  
Provides a PriorityQueue class that can be used to process higher priority 
items before lower priority items.

 * For a full description of the module, visit the [Project page](https://drupal.org/project/priority_queue)

 * To submit bug reports and feature suggestions, or to track changes go to
   the [Issue Tracker](https://www.drupal.org/project/issues/priority_queue?version=8.x)

## Requirements 

* None.

## Installation

Install priority_queue as usual. See the [Official documentation](https://www.drupal.org/docs/8/extending-drupal-8/installing-contributed-modules-find-import-enable-configure-drupal-8)
for furtherinformation.

Or use [Composer](https://getcomposer.org/).

```
composer require drupal/priority_queue
```

## How it works

```
$queue_factory = \Drupal::service('queue.priority_queue');
$queue = $queue_factory->get('test_queue');

$queue->createItem('LAST', -10);
$queue->createItem('FIRST', 10);
$queue->createItem('MIDDLE');

// Items will be processed by priority. In the above example the first item will be 'FIRST' since it has the highest priority of 10, and 'LAST' being last since it has the lowest priority of -10.
$item = $queue->claimItem();
```

## Contact

Current maintainers:
* [Julien Dubreuil](https://www.drupal.org/user/519520)
