<?php

declare(strict_types=1);

namespace Inspira\Collection;

use ArrayIterator;
use Closure;
use Inspira\Collection\Contracts\CollectionInterface;
use Inspira\Collection\Enums\Type;
use Inspira\Collection\Exceptions\ImmutableCollectionException;
use Inspira\Collection\Exceptions\InvalidLiteralTypeException;
use Inspira\Collection\Exceptions\InvalidTypeException;
use Inspira\Collection\Exceptions\ItemNotFoundException;
use Inspira\Collection\Traits\Whereable;
use Traversable;

/**
 * Collection class represents a collection of items with specified types.
 *
 * @template Item The type of collection item.
 * @package Inspira\Collection
 */
class Collection implements CollectionInterface
{
	use Whereable;

	/**
	 * Constructor for Collection.
	 *
	 * @param array|Item[]|CollectionInterface $items The initial set of items for the collection.
	 * @param class-string<Item>|Item|Type|string|int|float|bool|null|false $type The expected type of collection items.
	 * @param bool $isLiteralType Indicates whether the type is literal or not.
	 * @param bool $isMutable Indicates whether the collection is mutable or not.
	 */
	public function __construct(
		protected array|CollectionInterface       $items = [],
		protected Type|string|int|float|bool|null $type = Type::MIXED,
		protected bool                            $isLiteralType = false,
		protected bool                            $isMutable = false
	)
	{
		$this->items = $items instanceof CollectionInterface ? $items->toArray() : $items;
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
	 * @param Item $item The item to check.
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
	 * @param Item $item The item to get the type of.
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
	 * @return ?Item
	 * @throws ItemNotFoundException
	 */
	public function __get(string $name): mixed
	{
		if (isset($this->items[$name])) {
			return $this->items[$name];
		}

		throw new ItemNotFoundException("Item [$name] does not exist in the collection.");
	}

	/**
	 * @param string $name
	 * @param Item $value
	 * @return void
	 */
	public function __set(string $name, mixed $value): void
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
	 * @return Item[]
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
	 * @return Item
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
	 * @return ?Item
	 */
	public function first(): mixed
	{
		return reset($this->items) ?: null;
	}

	/**
	 * Gets the last item in the collection or null if the collection is empty.
	 *
	 * @return ?Item
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
	 * @return ?Item
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
	 * @param array|CollectionInterface $keys The keys to combine with the collection.
	 * @return static
	 */
	public function combine(array|CollectionInterface $keys): static
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
	 * @param Item $item The item to check for.
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
	 * @param Item $item The item to append.
	 * @return static<Item>
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
	 * @param Item $item The item to prepend.
	 * @return static<Item>
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
	 * Get the difference of two collections, merge them and return as a new Collection
	 *
	 * @param CollectionInterface $collection The collection to compare with.
	 * @param bool $checkType Determine whether to check the type of two collections.
	 * @return static
	 */
	public function diff(CollectionInterface $collection, bool $checkType = true): static
	{
		if ($checkType && ($aType = $this->getType()) !== ($bType = $collection->getType())) {
			[$aType, $bType] = $this->getActualAndExpectedTypeAsString($aType, $bType);
			throw new InvalidTypeException("Invalid type encountered during diff. Expecting type [$aType], [$bType] given.");
		}

		$a = array_udiff($this->items, $collection->toArray(), $this->getComparisonCallback());
		$b = array_udiff($collection->toArray(), $this->items, $this->getComparisonCallback());

		$collection = clone $this;
		$collection->items = [...$a, ...$b];
		$collection->type = TYPE::MIXED;

		return $collection;
	}

	/**
	 * Get the callback for comparing the items of 2 collections.
	 *
	 * @return Closure
	 */
	protected function getComparisonCallback(): Closure
	{
		return function (mixed $a, mixed $b): int {
			if (is_object($a) && is_object($b)) {
				$a = spl_object_id($a);
				$b = spl_object_id($b);
			}

			if (stringable($a) && stringable($b)) {
				return $a <=> $b;
			}

			if (gettype($a) !== gettype($b)) {
				return -1;
			}

			return $a <=> $b;
		};
	}
}
