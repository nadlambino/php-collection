<?php

declare(strict_types=1);

namespace Inspira\Collection;

use ArrayIterator;
use Closure;
use Inspira\Collection\Contracts\CollectionInterface;
use Inspira\Collection\Enums\Type;
use Inspira\Collection\Exceptions\InvalidLiteralTypeException;
use Inspira\Collection\Exceptions\InvalidTypeException;
use Inspira\Collection\Exceptions\ItemNotFoundException;
use Traversable;

/**
 * @template T The type of collection item.
 */
class GenericCollection implements CollectionInterface
{
	/**
	 * @param array<string|integer, T>|T[] $items
	 * @param T|Type|string|int|float|bool|null $type
	 * @param bool $isLiteralType
	 * @param bool $isMutable
	 */
	public function __construct(
		protected array $items = [],
		protected Type|string|int|float|bool|null $type = Type::MIXED,
		protected bool $isLiteralType = false,
		protected bool $isMutable = false
	)
	{
		$this->validateType();
	}

	protected function validateType()
	{
		$expectedType = $this->getType();
		if (empty($expectedType) || $expectedType === Type::MIXED->value || $this->isEmpty()) {
			return;
		}

		foreach ($this->items as $key => $item) {
			$actualType = $this->getItemType($item);

			if ($this->isValidType($item)) {
				continue;
			}

			if ($this->isLiteralType) {
				$actualType = is_object($actualType) ? get_class($actualType) : $actualType;
				throw new InvalidLiteralTypeException("Invalid item type encountered at position [$key]. Expecting literal [$expectedType], [$actualType] given.");
			}

			throw new InvalidTypeException("Invalid item type encountered at position [$key]. Expecting type [$expectedType], [$actualType] given.");
		}
	}

	protected function isValidType(mixed $item): bool
	{
		$actualType = $this->getItemType($item);
		$expectedType = $this->getType();

		return $expectedType === $actualType;
	}

	protected function getItemType(mixed $item)
	{
		if ($this->isLiteralType) {
			return $item;
		}

		return is_object($item) ? get_class($item) : gettype($item);
	}

	public function getType(): mixed
	{
		return $this->type instanceof Type ? $this->type->value : $this->type;
	}

	/**
	 * Get the item from the collection or throw an exception if it does not exist
	 * This is to differentiate the value between a null item and non-existing item
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

	public function __serialize(): array
	{
		return $this->items;
	}

	public function __unserialize(array $data): void
	{
		$this->items = $data;
	}

	public function toArray(): array
	{
		return $this->items;
	}

	public function count(): int
	{
		return count($this->items);
	}

	public function offsetExists(mixed $offset): bool
	{
		return isset($this->items[$offset]);
	}

	/**
	 * @param mixed $offset
	 * @return mixed|T
	 */
	public function offsetGet(mixed $offset): mixed
	{
		return $this->items[$offset] ?? null;
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		empty($offset) ? $this->items[] = $value : $this->items[$offset] = $value;
	}

	public function offsetUnset(mixed $offset): void
	{
		unset($this->items[$offset]);
	}

	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->items);
	}

	public function isEmpty(): bool
	{
		return $this->count() === 0;
	}

	/**
	 * @inheritdoc
	 * @return mixed|T
	 */
	public function first(): mixed
	{
		return reset($this->items) ?: null;
	}

	public static function make(array $data): static
	{
		return new static($data);
	}

	/**
	 * Get the last item in the collection or return null
	 *
	 * @return T|mixed
	 */
	public function last(): mixed
	{
		return end($this->items) ?: null;
	}

	/**
	 * @param int $index
	 * @param bool $strict
	 * @return T|mixed
	 */
	public function index(int $index, bool $strict = false): mixed
	{
		if (!$this->offsetExists($index) && $strict === true) {
			throw new ItemNotFoundException("Item at position [$index] does not exist in the collection.");
		}

		return $this->offsetGet($index);
	}

	public function column(string|int $column, string $key = null): static
	{
		$items = array_column($this->items, $column, $key);

		return new static($items);
	}

	public function chunk(int $length, bool $preserveKeys = false): static
	{
		$items = array_chunk($this->items, $length, $preserveKeys);

		return new static($items, Type::ARRAY);
	}

	/**
	 * @param Traversable|array $keys
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

		return new static($items, $this->type, $this->isLiteralType, $this->isMutable);
	}

	/**
	 * @inheritdoc
	 * @param T|mixed $item
	 * @return bool
	 */
	public function has(mixed $item): bool
	{
		return in_array($item, $this->items);
	}

	/**
	 * @inheritdoc
	 * @return static
	 */
	public function keys(): static
	{
		return new static(array_keys($this->items));
	}

	/**
	 * @inheritdoc
	 * @return static
	 */
	public function values(): static
	{
		return new static(array_values($this->items));
	}

	/**
	 * @inheritdoc
	 * @param T|mixed $item
	 * @return static
	 */
	public function append(mixed $item): static
	{
		$expectedType = $this->getType();
		if (!$this->isValidType($item)) {
			$actualType = $this->getItemType($item);
			$actualType = is_object($actualType) ? get_class($actualType) : $actualType;
			if ($this->isLiteralType) {
				throw new InvalidLiteralTypeException("Invalid item type encountered during append. Expecting literal [$expectedType], [$actualType] given.");
			}

			throw new InvalidTypeException("Invalid item type encountered during append. Expecting type [$expectedType], [$actualType] given.");
		}

		if ($this->isMutable) {
			$this->offsetSet(null, $item);

			return $this;
		}

		$items = $this->items;
		$items[] = $item;
		return new static($items, $this->type, $this->isLiteralType, $this->isMutable);
	}

	/**
	 * @inheritdoc
	 * @param T|mixed $item
	 * @return static
	 */
	public function prepend(mixed $item): static
	{
		$expectedType = $this->getType();
		if (!$this->isValidType($item)) {
			$actualType = $this->getItemType($item);
			$actualType = is_object($actualType) ? get_class($actualType) : $actualType;
			if ($this->isLiteralType) {
				throw new InvalidLiteralTypeException("Invalid item type encountered during preprend. Expecting literal [$expectedType], [$actualType] given.");
			}

			throw new InvalidTypeException("Invalid item type encountered during preprend. Expecting type [$expectedType], [$actualType] given.");
		}

		if ($this->isMutable) {
			array_unshift($this->items, $item);

			return $this;
		}

		$items = $this->items;
		array_unshift($items, $item);

		return new static($items, $this->type, $this->isLiteralType, $this->isMutable);
	}

	/**
	 * @inheritdoc
	 * @param int|string $key
	 * @return static
	 */
	public function unset(int|string $key): static
	{
		if ($this->isMutable) {
			$this->offsetUnset($key);

			return $this;
		}

		$items = $this->items;
		unset($items[$key]);

		return new static($items, $this->type, $this->isLiteralType, $this->isMutable);
	}

	public function where(string $column, mixed $comparison, mixed $value): static
	{
		// TODO: Implement where() method.
	}

	public function filter(callable|Closure $callback): static
	{
		// TODO: Implement filter() method.
	}
}
