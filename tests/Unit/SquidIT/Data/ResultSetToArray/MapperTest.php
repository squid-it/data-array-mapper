<?php

namespace Tests\Unit\SquidIT\Data\ResultSetToArray;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SquidIT\Data\ResultSetToArray\Mapper;

class MapperTest extends TestCase
{
	/**
	 * @var array $exampleDataSet
	 */
	private $exampleDataSet;

	/**
	 * @var array $resultStructureDbColumnToResult
	 */
	private $resultStructureDbColumnToResult;

	/**
	 * @var array
	 */
	private $resultStructureDbColumnToNewColumnResult;

	/**
	 * @var array $pivotPoints
	 */
	private $pivotPoints;

	/**
	 * setUp
	 *
	 * Load some default test values
	 */
	public function setUp(): void
	{
		// Load example dataset
		$this->exampleDataSet = require __DIR__.DIRECTORY_SEPARATOR.'ResultSet'.DIRECTORY_SEPARATOR.'dataset.php';

		// We want out resultSet to follow the below structure
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
				]
			]
		];

		// We want out resultSet to follow the below structure
		$this->resultStructureDbColumnToNewColumnResult = [
			'userId',
			'userName',
			'age' => 'leeftijd',
			'toys' => [
				'toyId',
				'toyType' => 'soort',
				'toyName',
				'placesToyVisited' => [
					'placeId',
					'placeName',
				]
			]
		];

		// We need to define our resultSet pivot points
		$this->pivotPoints = [
	  		'[root]' => 'userId',
	  		'toys' => 'toyId',
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
		$key = 'placeName';
		$value = '[userId].toys.[toyId].placesToyVisited.[placeId].placeName';

		$structure = Mapper::parseStructure($this->resultStructureDbColumnToResult, $this->pivotPoints);
		$this->assertArrayHasKey('userName', $structure);
		$this->assertArrayHasKey('placeId', $structure);
		$this->assertEquals($value, $structure[$key]);
	}

	public function testParseStructureColumnToNewColumn(): void
	{
		$key = 'toyType';
		$value = '[userId].toys.[toyId].soort';

		$structure = Mapper::parseStructure($this->resultStructureDbColumnToNewColumnResult, $this->pivotPoints);
		$this->assertEquals($value, $structure[$key]);
	}

	public function testMapData(): void
	{
		$structure = [
			'userId'	=> '[userId].userId',
			'placeId'	=> '[userId].toys.[toyId].placesToyVisited.[placeId].placeId',
		];

		$resultSet = Mapper::mapData($this->exampleDataSet, $structure);
		$firstRecord = reset($resultSet);
		$this->assertArrayHasKey('userId', $firstRecord);
		$this->assertEquals(3, $firstRecord['userId']);
		$this->assertEquals(33, $firstRecord['toys'][7]['placesToyVisited'][33]['placeId']);
	}

	public function testMapDataEmptyDataSet(): void
	{
		$structure = [
			'userId'	=> '[userId].userId',
			'placeId'	=> '[userId].toys.[toyId].placesToyVisited.[placeId].placeId',
		];

		$this->expectException(InvalidArgumentException::class);
		Mapper::mapData([], $structure);
	}

	public function testMapDataEmptyStructure(): void
	{
		$this->expectException(InvalidArgumentException::class);
		Mapper::mapData($this->exampleDataSet, []);
	}
}
