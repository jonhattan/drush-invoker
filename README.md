PHP class that invokes drush commands and allows to obtain the output (and logs) in a clean way.

## Example

```
<?php
$alias = '@com.example';
$command = 'status';
$options = array();
$arguments = array();

$drush = \DrushInvoker\DrushInvoker::invoke($alias, 'status', $options, $arguments);
$output = $drush->getOutput();

foreach ($drush->getLog() as $log) {
  print 'DRUSH:' . $log['type'] . ': ' . $log['message'];
}
```

