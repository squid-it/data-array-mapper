<?php

declare(strict_types=1);

namespace Tests\Unit\SquidIT\Data\ResultSetToArray;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquidIT\Data\ResultSetToArray\Mapper;

class MapperTest extends TestCase
{
    /** @var array<int, array<string, bool|int|string|null>> */
    private array $exampleDataSet;

    /** @var array<int, array<string, bool|int|string|null>> */
    private array $exampleDataSetInvalid;

    /** @var array<int, array<string, bool|int|string|null>> */
    private array $exampleDataSetUnordered;

    private string $dataSetPath = __DIR__ . '/ResultSet';

    /** @var array<int|string, array<mixed>|string> */
    private array $resultStructureDbColumnToResult;

    /** @var array<int|string, array<mixed>|string> */
    private array $resultStructureDbColumnToNewColumnResult;

    /** @var array<string, string> */
    private array $pivotPoints;

    /**
     * setUp
     *
     * Load some default test values
     */
    public function setUp(): void
    {
        // Load example datasets
        $this->exampleDataSet          = require $this->dataSetPath . '/dataset.php';
        $this->exampleDataSetInvalid   = require $this->dataSetPath . '/datasetInvalid.php';
        $this->exampleDataSetUnordered = require $this->dataSetPath . '/datasetUnordered.php';

        // We want our resultSet to follow the below structure
        $this->resultStructureDbColumnToResult = [
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
                ],
            ],
        ];

        // We want our resultSet to follow the below structure
        $this->resultStructureDbColumnToNewColumnResult = [
            'userId',
            'userName',
            'age'  => 'leeftijd',
            'toys' => [
                'toyId',
                'toyType' => 'soort',
                'toyName',
                'placesToyVisited' => [
                    'placeId',
                    'placeName',
                ],
            ],
        ];

        // We need to define our resultSet pivot points
        $this->pivotPoints = [
            '[root]'                => 'userId',
            'toys'                  => 'toyId',
            'toys.placesToyVisited' => 'placeId',
        ];
    }

    public function testParseStructureException(): void
    {
        $pivotPoints = $this->pivotPoints;
        unset($pivotPoints['[root]']);

        $this->expectException(InvalidArgumentException::class);
        Mapper::parseStructure($this->resultStructureDbColumnToResult, $pivotPoints);
    }

    public function testParseStructureColumnToResult(): void
    {
        $key   = 'placeName';
        $value = '[userId].toys.[toyId].placesToyVisited.[placeId].placeName';

        $structure = Mapper::parseStructure($this->resultStructureDbColumnToResult, $this->pivotPoints);

        self::assertArrayHasKey('userName', $structure);
        self::assertArrayHasKey('placeId', $structure);
        self::assertEquals($value, $structure[$key]);
    }

    public function testParseStructureColumnToNewColumn(): void
    {
        $key   = 'toyType';
        $value = '[userId].toys.[toyId].soort';

        $structure = Mapper::parseStructure($this->resultStructureDbColumnToNewColumnResult, $this->pivotPoints);
        self::assertEquals($value, $structure[$key]);
    }

    public function testMapData(): void
    {
        $structure = [
            'userId'  => '[userId].userId',
            'placeId' => '[userId].toys.[toyId].placesToyVisited.[placeId].placeId',
        ];

        $resultSet   = Mapper::mapData($this->exampleDataSet, $structure);
        $firstRecord = reset($resultSet);

        if (is_array($firstRecord) === false) {
            throw new RuntimeException('Unable to map data, record is supposed to be an array');
        }

        self::assertArrayHasKey('userId', $firstRecord);
        self::assertEquals(3, $firstRecord['userId']);
        self::assertEquals(33, $firstRecord['toys'][7]['placesToyVisited'][33]['placeId']);
    }

    public function testMapDataNoPivotKeyIdValue(): void
    {
        $structure = [
            'userId'  => '[userId].userId',
            'placeId' => '[userId].toys.[toyId].placesToyVisited.[placeId].placeId',
        ];

        $resultSet   = Mapper::mapData($this->exampleDataSet, $structure, false);
        $firstRecord = reset($resultSet);

        if (is_array($firstRecord) === false) {
            throw new RuntimeException('Unable to map data, record is supposed to be an array');
        }

        self::assertArrayHasKey(0, $firstRecord['toys']);
        self::assertArrayHasKey(1, $firstRecord['toys']);
    }

    public function testMapDataUnorderedDataSet(): void
    {
        $structure = [
            'userId'  => '[userId].userId',
            'placeId' => '[userId].toys.[toyId].placesToyVisited.[placeId].placeId',
        ];

        $resultSet   = Mapper::mapData($this->exampleDataSetUnordered, $structure);
        $firstRecord = reset($resultSet);

        if (is_array($firstRecord) === false) {
            throw new RuntimeException('Unable to map data, record is supposed to be an array');
        }

        self::assertArrayHasKey('userId', $firstRecord);
        self::assertEquals(3, $firstRecord['userId']);
        self::assertEquals(33, $firstRecord['toys'][7]['placesToyVisited'][33]['placeId']);
    }

    public function testMapDataUnorderedDataSetNoPivotKeyIdValue(): void
    {
        $structure   = Mapper::parseStructure($this->resultStructureDbColumnToResult, $this->pivotPoints);
        $resultSet   = Mapper::mapData($this->exampleDataSetUnordered, $structure, false);
        $firstRecord = reset($resultSet);

        if (is_array($firstRecord) === false) {
            throw new RuntimeException('Unable to map data, record is supposed to be an array');
        }

        self::assertArrayHasKey(0, $firstRecord['toys']);

        foreach ($firstRecord['toys'] as $toy) {
            if ($toy['toyId'] !== 7) {
                continue;
            }

            self::assertCount(2, $toy['placesToyVisited']);
        }
    }

    public function testMapDataEmptyDataSet(): void
    {
        $structure = [
            'userId'  => '[userId].userId',
            'placeId' => '[userId].toys.[toyId].placesToyVisited.[placeId].placeId',
        ];

        $this->expectException(InvalidArgumentException::class);
        Mapper::mapData([], $structure);
    }

    public function testMapDataMissingDataSetColumns(): void
    {
        $structure = Mapper::parseStructure($this->resultStructureDbColumnToResult, $this->pivotPoints);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not all required columns are present');
        Mapper::mapData($this->exampleDataSetInvalid, $structure);
    }

    public function testMapDataEmptyStructure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Mapper::mapData($this->exampleDataSet, []);
    }
}
