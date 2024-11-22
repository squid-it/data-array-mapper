# Squid - Database ResultSet to Multidimensional Array Mapper
Convert a database result set to a multidimensional array

This package allows you to quickly convert a database result set into a multidimensional array.
This is useful when you need to output nested json for example.

### Example data set & end result
```php
<?php

$resultSet = [
    [
        'userId'    => 3,
        'userName'  => 'MoròSwitie',
        'age'       => 37,
        'toyId'     => 7,
        'toyType'   => 'car',
        'toyName'   => 'Rover',
        'placeId'   => 33,
        'placeName' => 'Australia',
    ],
    [
        'userId'    => 3,
        'userName'  => 'MoròSwitie',
        'age'       => 37,
        'toyId'     => 7,
        'toyType'   => 'car',
        'toyName'   => 'Rover',
        'placeId'   => 34,
        'placeName' => 'New Zealand',
    ]
];

$finalResult = [
    3 => [
        'userId'    => 3,
        'userName'  =>'MoròSwitie',
        'age'       => 37,
        'toys'      => [
            7 => [
                'toyId'     => 7,
                'toyType'   => 'car',
                'toyName'   => 'Rover',
                'placesToyVisited' => [
                    33 => [
                        'placeId'   => 33,
                        'placeName' => 'Australia',
                    ],
                    34 => [
                        'placeId'   => 34,
                        'placeName' => 'New Zealand',
                    ],
                ]
            ]
        ]
    ]
];
```

### Example Usage
Before we can map our result set into our new structure we need to describe how our end result should look.
We also need to supply the path to our pivot points.

We will be using "dot" notation to describe our pivot points. It is important to note that a "['root']" key must
be present in our $pivotPoints array.

The pivotPoints will be used to group the result set.
#### Rule of Thumb:
for each key in your $resultStructure array that isn't a column, you need to specify a pivot point.
In the below $resultStructure array we got 3 pivot points.
1. "['root']" (this one is not actually in our array but we need to define it)
2. "toys"
3. "placesToyVisited"

```php
<?php

$resultStructure = [
    'userId',
    'userName',
    'age',
    'toys' => [
        'toyId',
        'toyType',
        'toyName',
        'placesToyVisited' => [
            'placeId',
            'placeName',
        ]
    ]
];

$pivotPoints = [
    '[root]' => 'userId',
    'toys' => 'toyId',
    'toys.placesToyVisited' => 'placeId',
];
```

To get our end result we need to do this
```php
<?php

// Make sure composer autoload has loaded
use SquidIT\Data\ResultSetToArray\Mapper;

$parsedStructure = Mapper::parseStructure($resultStructure, $pivotPoints);
$mappedData      = Mapper::mapData($resultSet, $parsedStructure);
```

If we do not want to have our pivoted data to be prefixed by the pivot point id value, we can add an extra parameter
"false" to the `Mapper::mapData` method.

This will ensure that when using json_encode the resulting object will contain arrays for all pivoted data.
```php
<?php

// Make sure composer autoload has loaded
use SquidIT\Data\ResultSetToArray\Mapper;

$parsedStructure = Mapper::parseStructure($resultStructure, $pivotPoints);
$mappedData      = Mapper::mapData($resultSet, $parsedStructure, false);
```

##### Specifying a different column name:
It is possible to use a different column name in our mapped data result. To do this, we can specify how we want to name
the new column.

```php
<?php

$resultStructure = [
    'userId' => 'accountId', // dataset column = 'userId', output would be 'accountId'
    'userName' => 'accountName',
    'age' => 'accountCreationDate',
    'toys' => [
        'toyId',
        'toyType' => 'specimen',
        'toyName',
        'placesToyVisited' => [
            'placeId',
            'placeName',
        ]
    ]
];

$pivotPoints = [
    '[root]' => 'userId',
    'toys' => 'toyId',
    'toys.placesToyVisited' => 'placeId',
];
```
