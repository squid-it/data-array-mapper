<?php
/**
 * @package ResultSetToArray
 * @author Cecil Zorg <developer@squidit.nl>
 * @version 0.1 2020-01-18
 */
declare(strict_types=1);

namespace SquidIT\Data\ResultSetToArray;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

/**
 * Class Mapper
 * @package SquidIT\Data\ResultSetToArray
 */
class Mapper
{
	/**
	 * @var string $sep the separator used
	 */
	public static $sep = '.';

	/**
	 * parseStructure
	 *
	 * Generates a structure with columnNames and the path in . dot notation
	 *
	 * example input structure:
	 * $resultStructure = [
	 * 	'userId',
	 * 	'userName',
	 * 	'age',
	 * 	'toys' => [
	 * 		'toyId',
	 * 		'toyType',
	 * 		'toyName',
	 * 		'placesToyVisited' => [
	 * 			'placeId',
	 * 			'placeName',
	 * 		]
	 * 	]
	 * ];
	 *
	 * $pivotPoints = [
	 * 	'[root]' => 'userId',
	 * 	'toys' => 'toyId',
	 * 	'toys.placesToyVisited' => 'placeId',
	 * ];
	 *
	 * @param array $resultStructure describes how our end result needs to look
	 * @param array $pivotPoints
	 * @return array
	 */
	public static function parseStructure(array $resultStructure, array $pivotPoints): array
	{
		if (!isset($pivotPoints['[root]'])) {
			throw new \InvalidArgumentException('Could not generate resultStructure no root pivot point supplied');
		}

		$path 		= [];
		$cleanPath	= [];
		$flatArray	= [];

		$resultStructureIterator	= new RecursiveArrayIterator($resultStructure);
		$recursiveIterator			= new RecursiveIteratorIterator(
			$resultStructureIterator,
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($recursiveIterator as $key => $structure) {
			$depth = $recursiveIterator->getDepth();

			if ($depth === 0) {
				$path[$depth] = '['.$pivotPoints['[root]'].']';
			}

			$keyType = gettype($key);
			$structureType = gettype($structure);

			if ($keyType === 'string' && $structureType === 'array') {
				$cleanPath[$depth]	= $key;
				$point				= implode(self::$sep, $cleanPath);

				if ($depth === 0) {
					$path[$depth] = '['.$pivotPoints['[root]'].']'.self::$sep.$key.'.['.$pivotPoints[$point].']';
				} else {
					$path[$depth] = $key.'.['.$pivotPoints[$point].']';
				}
			}

			if ($structureType !== 'array') {
				$structureName = ($keyType === 'integer') ? $structure : $key;

				$flatArray[$structureName] = implode(
					self::$sep,
					array_slice(
						$path,
						0,
						$recursiveIterator->getDepth() + 1
					)
				).self::$sep.$structure;
			}
		}

		return $flatArray;
	}

	/**
	 * mapData
	 *
	 * Generates the mapped result set
	 *
	 * @param array $dataSet the db result set
	 * @param array $parsedStructure  array returned by self::parseStructure
	 * @return array
	 */
	public static function mapData(array $dataSet, array $parsedStructure): array
	{
		$resultSet = [];

		if (empty($dataSet)) {
			throw new \InvalidArgumentException('dataSet can not be empty');
		}

		if (empty($parsedStructure)) {
			throw new \InvalidArgumentException('parsedStructure can not be empty');
		}

		foreach ($dataSet as $dataRecord) {
			foreach ($parsedStructure as $columnName => $path) {
				self::setValue($resultSet, $path, $columnName, $dataRecord);
			}
		}

		return $resultSet;
	}

	/**
	 * setValue
	 *
	 * Sets the value in our array based on path, path items enclosed by brackets will be substituted by the identifier
	 * which should be available inside the $record array
	 *
	 * example: $record = ['userId' => 3, 'toyId' => 187, 'toyName' = 'car']
	 * example: $path = [userId].toys.[toyId].toyName
	 * example: $columnName = 'toyName'
	 *
	 * $resultSet = [
	 * 	3 => [
	 * 		'toys' => [
	 * 			187 => [
	 * 				'toyName' => 'car'
	 * 			]
	 * 		]
	 * ]
	 *
	 * @param array $resultSet the array that will hold the end result
	 * @param string $path path to our value
	 * @param string $columnName columnName of the value to use
	 * @param array $record data record holding all column names as keys and there values
	 */
	protected static function setValue(array &$resultSet, string $path, string $columnName, array $record): void
	{
		// get all parts of our path
		$keys = explode(self::$sep, $path);

		foreach ($keys as $key) {
			// Replace primary key identifiers
			if ($key[0] === '[' && $key[-1] === ']') {
				$key = substr($key, 1, -1);

				if (isset($record[$key])) {
					$key = $record[$key];
				} else {
					return;
				}
			}

			// create reference to positions in our array
			$resultSet = &$resultSet[$key];
		}

		// we are at the end of our array, set value
		$resultSet = $record[$columnName];
	}
}
