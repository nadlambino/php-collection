<?php

namespace Inspira\Collection\Traits;

use ArrayAccess;
use ArrayObject;
use Closure;
use Error;
use InvalidArgumentException;
use Inspira\Collection\Enums\Type;
use Inspira\Contracts\Arrayable;
use function Inspira\Utils\stringable;

/**
 * @property $items
 * @property $isMutable
 * @method string getType
 * @method array getActualAndExpectedTypeAsString($actual, $expected)
 */
trait Whereable
{
	/**
	 * Filters the collection based on a given condition.
	 * When only 1 argument is given, it will be used as the value to compare against the item
	 *
	 * When the first argument is a Closure, all other arguments will be ignored. The closure
	 * will then be directly passed to the $this->filter method.
	 *
	 * When 2 arguments is given, the first argument will be the column and the second argument
	 * will be the value. The default comparison operator is "="|"==" which does a loose comparison.
	 *
	 * @param string|Closure $column The column to filter on or the value to compare against or the closure to passed on the filter.
	 * @param mixed $comparison The comparison operator or the value to compare against.
	 * @param mixed $value The value to compare against.
	 * @return static
	 */
	public function where(string|Closure $column, mixed $comparison = null, mixed $value = null): static
	{
		if (is_string($column)) {
			$argsCount = func_num_args();
			$newComparison = match (true) {
				$argsCount === 2,
				empty($comparison) => '=',
				default => $comparison
			};
			$value = match (true) {
				$argsCount === 1 => $column,
				$argsCount === 2 => $comparison,
				default => $value
			};
			$callback = $this->getFilterCallback($column, $newComparison, $value);
		}

		return $this->filter($callback ?? $column);
	}

	/**
	 * Filters the collection to include only items where the specified column is like the given value.
	 * When only 1 argument is given, it will be used as the value to compare against the item
	 *
	 * @param string|null $column The column to filter on or the value to compare against the item if it's not an object or associative array.
	 * @param string $value The value to compare against.
	 * @param bool $strict Indicates whether to perform a strict comparison.
	 * @return static The filtered collection.
	 */
	public function whereLike(?string $column, string $value, bool $strict = false): static
	{
		$comparison = match (true) {
			str_starts_with($value, '%') && str_ends_with($value, '%') => '%LIKE%',
			str_starts_with($value, '%') => 'LIKE%',
			str_ends_with($value, '%') => '%LIKE',
			default => '%LIKE%'
		};

		return $this->filter($this->getFilterCallback($column, $comparison, trim($value, '%'), $strict));
	}

	/**
	 * Filters the collection to exclude items where the specified column is like the given value.
	 * When only 1 argument is given, it will be used as the value to compare against the item
	 *
	 * @param string|null $column
	 * @param string $value The value to compare against.
	 * @param bool $strict Indicates whether to perform a strict comparison.
	 * @return static The filtered collection.
	 */
	public function whereNotLike(?string $column, string $value, bool $strict = false): static
	{
		$comparison = match (true) {
			str_starts_with($value, '%') && str_ends_with($value, '%') => '%NOT_LIKE%',
			str_starts_with($value, '%') => 'NOT_LIKE%',
			str_ends_with($value, '%') => '%NOT_LIKE',
			default => '%NOT_LIKE%'
		};

		return $this->filter($this->getFilterCallback($column, $comparison, trim($value, '%'), $strict));
	}

	/**
	 * Filters the collection to include only items where the specified column is null.
	 *
	 * @param string|null $column The column to filter on.
	 * @return static The filtered collection.
	 */
	public function whereNull(?string $column): static
	{
		return $this->filter($this->getFilterCallback($column, '=', null));
	}

	/**
	 * Filters the collection to exclude items where the specified column is null.
	 *
	 * @param string|null $column The column to filter on.
	 * @return static The filtered collection.
	 */
	public function whereNotNull(?string $column): static
	{
		return $this->filter($this->getFilterCallback($column, '!=', null));
	}

	/**
	 * Filters the collection to include only items where the specified column is between the given bounds.
	 *
	 * @param string|null $column The column to filter on.
	 * @param mixed $lowerBound The lower bound of the range.
	 * @param mixed $upperBound The upper bound of the range.
	 * @return static The filtered collection.
	 */
	public function whereBetween(?string $column, mixed $lowerBound, mixed $upperBound): static
	{
		return $this->filter($this->getFilterCallback($column, 'BETWEEN', [$lowerBound, $upperBound]));
	}

	/**
	 * Filters the collection to exclude items where the specified column is between the given bounds.
	 *
	 * @param string|null $column The column to filter on.
	 * @param mixed $lowerBound The lower bound of the range.
	 * @param mixed $upperBound The upper bound of the range.
	 * @return static The filtered collection.
	 */
	public function whereNotBetween(?string $column, mixed $lowerBound, mixed $upperBound): static
	{
		return $this->filter($this->getFilterCallback($column, 'NOT_BETWEEN', [$lowerBound, $upperBound]));
	}

	/**
	 * Filters the collection to include only items where the specified column is in the given array of values.
	 *
	 * @param string|null $column The column to filter on.
	 * @param array $values The array of values to check against.
	 * @return static The filtered collection.
	 */
	public function whereIn(?string $column, array $values): static
	{
		return $this->filter($this->getFilterCallback($column, 'IN', $values));
	}

	/**
	 * Filters the collection to exclude items where the specified column is in the given array of values.
	 *
	 * @param string|null $column The column to filter on.
	 * @param array $values The array of values to check against.
	 * @return static The filtered collection.
	 */
	public function whereNotIn(?string $column, array $values): static
	{
		return $this->filter($this->getFilterCallback($column, 'NOT_IN', $values));
	}

	/**
	 * Creates a callback function for filtering the collection based on a specified condition.
	 *
	 * @param string|null $column The column to filter on.
	 * @param string $comparison The comparison value.
	 * @param mixed $search The value to compare against.
	 * @param bool $strict Indicates whether to perform a strict comparison.
	 * @return Closure The callback function for filtering the collection.
	 */
	protected function getFilterCallback(?string $column, string $comparison, mixed $search, bool $strict = false): Closure
	{
		return function ($item) use ($column, $comparison, $search, $strict): bool {
			$value = $this->getItemValue($item, $column);
			$value = $strict === false && is_string($value) ? strtolower($value) : $value;
			$search = $strict === false && is_string($search) ? strtolower($search) : $search;

			return match ($comparison) {
				'=',
				'==' => $value == $search,
				'===' => $value === $search,
				'<>',
				'!=',
				'!==' => $value !== $search,
				'>' => $value > $search,
				'<' => $value < $search,
				'>=' => $value >= $search,
				'<=' => $value <= $search,
				'BETWEEN' => $value >= $search[0] ?? null && $value <= $search[1] ?? null,
				'NOT_BETWEEN' => $value < $search[0] ?? null && $value > $search[1] ?? null,
				'IN' => in_array($value, $search),
				'NOT_IN' => !in_array($value, $search),
				'%LIKE%' => str_contains((string)$value, (string)$search),
				'LIKE%' => str_starts_with((string)$value, (string)$search),
				'%LIKE' => str_ends_with((string)$value, (string)$search),
				'%NOT_LIKE%' => !str_contains((string)$value, (string)$search),
				'NOT_LIKE%' => !str_starts_with((string)$value, (string)$search),
				'%NOT_LIKE' => !str_ends_with((string)$value, (string)$search),
				default => false,
			};
		};
	}

	/**
	 * Gets the value of a specified column from an item in the collection.
	 *
	 * @param mixed $item The item to retrieve the value from.
	 * @param string|null $column The name of the column to get the value from.
	 * @return mixed The value of the specified column in the item.
	 */
	protected function getItemValue(mixed $item, ?string $column): mixed
	{
		$expectedType = $this->getType();
		$actualType = gettype($item);
		[$actual] = $this->getActualAndExpectedTypeAsString($actualType, $expectedType);

		return match (true) {
			// Handle stringable item type on an empty column name
			empty($column) && stringable($item) => $item,

			// Throw exception when the column is not provided and the item is not stringable
			empty($column) && !stringable($item) => throw new InvalidArgumentException("Cannot provide an empty column name to a non stringable items."),

			// Object item type
			is_object($item),
			$item instanceof ArrayObject,
			$actualType === Type::OBJECT->value,
			$expectedType === Type::OBJECT->value => $item->$column,

			// Array item type
			is_array($item),
			$item instanceof ArrayAccess,
			$actualType === Type::ARRAY->value,
			$expectedType === Type::ARRAY->value => $item[$column],
			$item instanceof Arrayable => $item->toArray()[$column],

			// Unhandled item types
			default => throw new Error("Cannot find column [$column] in collection item type [$actual].")
		};
	}

	/**
	 * Filters the collection using a callback function.
	 *
	 * @param callable|Closure $callback The callback function to use for filtering.
	 * @return static
	 */
	public function filter(callable|Closure $callback): static
	{
		$items = array_filter($this->items, $callback);

		if ($this->isMutable) {
			$this->items = $items;
			return $this;
		}

		$collection = clone $this;
		$collection->items = $items;

		return $collection;
	}
}
