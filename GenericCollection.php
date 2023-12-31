<?php

declare(strict_types=1);

namespace Inspira\Collection;

use ArrayAccess;
use ArrayIterator;
use ArrayObject;
use Closure;
use Error;
use Inspira\Collection\Contracts\CollectionInterface;
use Inspira\Collection\Enums\Type;
use Inspira\Collection\Exceptions\ImmutableCollectionException;
use Inspira\Collection\Exceptions\InvalidLiteralTypeException;
use Inspira\Collection\Exceptions\InvalidTypeException;
use Inspira\Collection\Exceptions\ItemNotFoundException;
use Inspira\Contracts\Arrayable;
use Traversable;

/**
 * GenericCollection class represents a collection of items with specified types.
 *
 * @template T The type of collection item.
 * @package Inspira\Collection
 */
class GenericCollection implements CollectionInterface
{
	/**
	 * Constructor for GenericCollection.
	 *
	 * @param array<string|integer, T>|T[] $items The initial set of items for the collection.
	 * @param T|Type|string|int|float|bool|null $type The expected type of collection items.
	 * @param bool $isLiteralType Indicates whether the type is literal or not.
	 * @param bool $isMutable Indicates whether the collection is mutable or not.
	 */
	public function __construct(
		protected array                           $items = [],
		protected Type|string|int|float|bool|null $type = Type::MIXED,
		protected bool                            $isLiteralType = false,
		protected bool                            $isMutable = false
	)
	{
		$this->validateType();
	}

	/**
	 * Validates the type of each item in the collection against the expected type.
	 *
	 * @throws InvalidLiteralTypeException
	 * @throws InvalidTypeException
	 */
	protected function validateType()
	{
		$expectedType = $this->getType();
		if ($this->isEmpty()) {
			return;
		}

		foreach ($this->items as $key => $item) {
			$actualType = $this->getItemType($item);

			if ($this->isValidType($item)) {
				continue;
			}

			if ($this->isLiteralType) {
				[$actualType, $expectedType] = $this->getActualAndExpectedTypeAsString($actualType, $expectedType);
				throw new InvalidLiteralTypeException("Invalid item type encountered at position [$key]. Expecting literal [$expectedType], [$actualType] given.");
			}

			throw new InvalidTypeException("Invalid item type encountered at position [$key]. Expecting type [$expectedType], [$actualType] given.");
		}
	}

	/**
	 * Get a string representation of actual and expected literal type
	 *
	 * @param mixed $actual
	 * @param mixed $expected
	 * @return array
	 */
	protected function getActualAndExpectedTypeAsString(mixed $actual, mixed $expected): array
	{
		if ($this->isLiteralType) {
			return [json_encode($actual), json_encode($expected)];
		}

		return [$actual, $expected];
	}

	/**
	 * Checks if the given item is of a valid type.
	 *
	 * @param mixed $item The item to check.
	 * @return bool
	 */
	protected function isValidType(mixed $item): bool
	{
		$actualType = $this->getItemType($item);
		$expectedType = $this->getType();

		return $expectedType === '' || $expectedType === Type::MIXED->value || $expectedType === $actualType;
	}

	/**
	 * Gets the type of the given item.
	 *
	 * @param mixed $item The item to get the type of.
	 * @return mixed
	 */
	protected function getItemType(mixed $item): mixed
	{
		if ($this->isLiteralType) {
			return $item;
		}

		return is_object($item) ? get_class($item) : gettype($item);
	}

	/**
	 * Gets the type of the collection.
	 *
	 * @return mixed
	 */
	public function getType(): mixed
	{
		return $this->type instanceof Type ? $this->type->value : $this->type;
	}

	/**
	 * Magic method to get the item from the collection or throw an exception if it does not exist.
	 *
	 * @param string $name The name of the collection item.
	 * @return T|mixed
	 * @throws ItemNotFoundException
	 */
	public function __get(string $name): mixed
	{
		if (isset($this->items[$name])) {
			return $this->items[$name];
		}

		throw new ItemNotFoundException("Item [$name] does not exist in the collection.");
	}

	public function __set(string $name, $value): void
	{
		if ($this->isValidType($value)) {
			$this->items[$name] = $value;
			return;
		}

		$expectedType = $this->getType();
		$actualType = $this->getItemType($value);

		if ($this->isLiteralType) {
			[$actualType, $expectedType] = $this->getActualAndExpectedTypeAsString($actualType, $expectedType);
			throw new InvalidLiteralTypeException("Invalid item type encountered during __set. Expecting literal [$expectedType], [$actualType] given.");
		}

		throw new InvalidTypeException("Invalid item type encountered during __set. Expecting type [$expectedType], [$actualType] given.");
	}

	/**
	 * Serializes the collection to an array.
	 *
	 * @return array
	 */
	public function __serialize(): array
	{
		return $this->items;
	}

	/**
	 * Unserializes the collection from an array.
	 *
	 * @param array $data The data to unserialize.
	 */
	public function __unserialize(array $data): void
	{
		$this->items = $data;
	}

	/**
	 * Converts the collection to an array.
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		return $this->items;
	}

	/**
	 * Gets the count of items in the collection.
	 *
	 * @return int
	 */
	public function count(): int
	{
		return count($this->items);
	}

	/**
	 * Checks if an offset exists in the collection.
	 *
	 * @param mixed $offset The offset to check.
	 * @return bool
	 */
	public function offsetExists(mixed $offset): bool
	{
		return isset($this->items[$offset]);
	}

	/**
	 * Gets the item at the specified offset.
	 *
	 * @param mixed $offset The offset to retrieve.
	 * @return mixed|T
	 */
	public function offsetGet(mixed $offset): mixed
	{
		return $this->items[$offset] ?? null;
	}

	/**
	 * Sets the value at the specified offset.
	 * If offset is empty, push the value to items.
	 *
	 * @param mixed $offset The offset to set.
	 * @param mixed $value The value to set.
	 */
	public function offsetSet(mixed $offset, mixed $value): void
	{
		if ($this->isMutable === false) {
			throw new ImmutableCollectionException("Cannot set an item of an immutable collection.");
		}

		empty($offset) ? $this->items[] = $value : $this->items[$offset] = $value;
	}

	/**
	 * Unsets the item at the specified offset.
	 *
	 * @param mixed $offset The offset to unset.
	 */
	public function offsetUnset(mixed $offset): void
	{
		if ($this->isMutable === false) {
			throw new ImmutableCollectionException("Cannot unset an item of immutable collection.");
		}

		unset($this->items[$offset]);
	}

	/**
	 * Gets an iterator for the collection.
	 *
	 * @return Traversable
	 */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->items);
	}

	/**
	 * Checks if the collection is empty.
	 *
	 * @return bool
	 */
	public function isEmpty(): bool
	{
		return $this->count() === 0;
	}

	/**
	 * Gets the first item in the collection or null if the collection is empty.
	 *
	 * @return T|mixed|null
	 */
	public function first(): mixed
	{
		return reset($this->items) ?: null;
	}

	/**
	 * Creates a new GenericCollection instance from the given array.
	 *
	 * @param array $data The array to create the collection from.
	 * @return static
	 */
	public static function make(array $data): static
	{
		return new static($data);
	}

	/**
	 * Gets the last item in the collection or null if the collection is empty.
	 *
	 * @return T|mixed|null
	 */
	public function last(): mixed
	{
		return end($this->items) ?: null;
	}

	/**
	 * Gets the item at the specified index in the collection.
	 *
	 * @param int $index The index to retrieve.
	 * @param bool $strict Indicates whether to throw an exception if the index does not exist.
	 * @return T|mixed
	 * @throws ItemNotFoundException
	 */
	public function index(int $index, bool $strict = false): mixed
	{
		if (!$this->offsetExists($index) && $strict === true) {
			throw new ItemNotFoundException("Item at position [$index] does not exist in the collection.");
		}

		return $this->offsetGet($index);
	}

	/**
	 * Extracts a column from the items in the collection.
	 *
	 * @param int|string $column The column to extract.
	 * @param ?string $key The key to use as the index for the returned array.
	 * @return static
	 */
	public function column(string|int $column, string $key = null): static
	{
		$items = array_column($this->items, $column, $key);

		return new static($items);
	}

	/**
	 * Chunks the collection into smaller collections.
	 *
	 * @param int $length The size of each chunk.
	 * @param bool $preserveKeys Whether to preserve the keys of the original collection.
	 * @return static
	 */
	public function chunk(int $length, bool $preserveKeys = false): static
	{
		$items = array_chunk($this->items, $length, $preserveKeys);

		return new static($items, Type::ARRAY);
	}

	/**
	 * Combines the collection with the keys provided.
	 *
	 * @param Traversable|array $keys The keys to combine with the collection.
	 * @return static
	 */
	public function combine(Traversable|array $keys): static
	{
		$keys = is_array($keys) ? $keys : iterator_to_array($keys);
		$items = $this->items;
		$items = array_combine($keys, $items);

		if ($this->isMutable) {
			$this->items = $items;
			return $this;
		}

		$collection = clone $this;
		$collection->items = $items;

		/** @var static */
		return $collection;
	}

	/**
	 * Checks if the collection contains a given item.
	 *
	 * @param T|mixed $item The item to check for.
	 * @return bool
	 */
	public function has(mixed $item): bool
	{
		return in_array($item, $this->items);
	}

	/**
	 * Gets a collection of the keys in the current collection.
	 *
	 * @return static
	 */
	public function keys(): static
	{
		return new static(array_keys($this->items));
	}

	/**
	 * Gets a collection of the values in the current collection.
	 *
	 * @return static
	 */
	public function values(): static
	{
		return new static(array_values($this->items));
	}

	/**
	 * Appends an item to the collection.
	 *
	 * @param T|mixed $item The item to append.
	 * @return static
	 * @throws InvalidLiteralTypeException
	 * @throws InvalidTypeException
	 */
	public function append(mixed $item): static
	{
		$expectedType = $this->getType();
		if (!$this->isValidType($item)) {
			$actualType = $this->getItemType($item);
			$actualType = is_object($actualType) ? get_class($actualType) : $actualType;

			if ($this->isLiteralType) {
				[$actualType, $expectedType] = $this->getActualAndExpectedTypeAsString($actualType, $expectedType);
				throw new InvalidLiteralTypeException("Invalid item type encountered during append. Expecting literal [$expectedType], [$actualType] given.");
			}

			throw new InvalidTypeException("Invalid item type encountered during append. Expecting type [$expectedType], [$actualType] given.");
		}

		if ($this->isMutable) {
			$this->offsetSet(null, $item);

			return $this;
		}

		$collection = clone $this;
		$collection->items[] = $item;

		/** @var static */
		return $collection;
	}

	/**
	 * Prepends an item to the collection.
	 *
	 * @param T|mixed $item The item to prepend.
	 * @return static
	 * @throws InvalidLiteralTypeException
	 * @throws InvalidTypeException
	 */
	public function prepend(mixed $item): static
	{
		$expectedType = $this->getType();
		if (!$this->isValidType($item)) {
			$actualType = $this->getItemType($item);
			$actualType = is_object($actualType) ? get_class($actualType) : $actualType;

			if ($this->isLiteralType) {
				[$actualType, $expectedType] = $this->getActualAndExpectedTypeAsString($actualType, $expectedType);
				throw new InvalidLiteralTypeException("Invalid item type encountered during prepend. Expecting literal [$expectedType], [$actualType] given.");
			}

			throw new InvalidTypeException("Invalid item type encountered during prepend. Expecting type [$expectedType], [$actualType] given.");
		}

		if ($this->isMutable) {
			array_unshift($this->items, $item);

			return $this;
		}

		$collection = clone $this;
		array_unshift($collection->items, $item);

		return $collection;
	}

	/**
	 * Removes the item at the specified key from the collection.
	 *
	 * @param int|string $key The key to unset.
	 * @return static
	 */
	public function unset(int|string $key): static
	{
		if ($this->isMutable) {
			$this->offsetUnset($key);

			return $this;
		}

		$collection = clone $this;
		unset($collection->items[$key]);

		return $collection;
	}

	/**
	 * Filters the collection based on a given condition.
	 *
	 * @param string|Closure $column The column to filter on.
	 * @param mixed $comparison The comparison value.
	 * @param mixed $value The value to compare against.
	 * @return static
	 */
	public function where(string|Closure $column, mixed $comparison, mixed $value = null): static
	{
		if (is_string($column)) {
			$newComparison = func_num_args() === 2 ? '=' : $comparison;
			$value = func_num_args() === 2 ? $comparison : $value;
			$column = $this->getFilterCallback($column, $newComparison, $value);
		}

		return $this->filter($column);
	}

	protected function getFilterCallback(string $column, string $comparison, mixed $search): Closure
	{
		return function ($item) use ($column, $comparison, $search) {
			$value = $this->getItemValue($item, $column);
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
				'like' => str_contains((string)$value, (string)$search),
				'not_like' => !str_contains((string)$value, (string)$search),
				'starts_with' => str_starts_with((string)$value, (string)$search),
				'ends_with' => str_ends_with((string)$value, (string)$search),
				default => false,
			};
		};
	}

	protected function getItemValue(mixed $item, string $column)
	{
		$expectedType = $this->getType();
		$actualType = gettype($item);
		[$actual] = $this->getActualAndExpectedTypeAsString($actualType, $expectedType);

		return match (true) {
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

	public function orWhere(string|Closure $column, mixed $comparison = null, mixed $value = null): static
	{
		// TODO: Implement orWhere() method.
	}

	public function whereLike(string $column, string $value): static
	{
		// TODO: Implement whereLike() method.
	}

	public function whereNotLike(string $column, string $value): static
	{
		// TODO: Implement whereNotLike() method.
	}

	public function orWhereLike(string $column, string $value): static
	{
		// TODO: Implement orWhereLike() method.
	}

	public function orWhereNotLike(string $column, string $value): static
	{
		// TODO: Implement orWhereNotLike() method.
	}

	public function whereNull(string $column): static
	{
		// TODO: Implement whereNull() method.
	}

	public function whereNotNull(string $column): static
	{
		// TODO: Implement whereNotNull() method.
	}

	public function orWhereNull(string $column): static
	{
		// TODO: Implement orWhereNull() method.
	}

	public function orWhereNotNull(string $column): static
	{
		// TODO: Implement orWhereNotNull() method.
	}

	public function whereBetween(string $column, mixed $lowerBound, mixed $upperBound): static
	{
		// TODO: Implement whereBetween() method.
	}

	public function whereNotBetween(string $column, mixed $lowerBound, mixed $upperBound): static
	{
		// TODO: Implement whereNotBetween() method.
	}

	public function orWhereBetween(string $column, mixed $lowerBound, mixed $upperBound): static
	{
		// TODO: Implement orWhereBetween() method.
	}

	public function orWhereNotBetween(string $column, mixed $lowerBound, mixed $upperBound): static
	{
		// TODO: Implement orWhereNotBetween() method.
	}

	public function whereIn(string $column, array $values): static
	{
		// TODO: Implement whereIn() method.
	}

	public function whereNotIn(string $column, array $values): static
	{
		// TODO: Implement whereNotIn() method.
	}

	public function orWhereIn(string $column, array $values): static
	{
		// TODO: Implement orWhereIn() method.
	}

	public function orWhereNotIn(string $column, array $values): static
	{
		// TODO: Implement orWhereNotIn() method.
	}
}
