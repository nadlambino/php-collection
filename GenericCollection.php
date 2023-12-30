<?php

declare(strict_types=1);

namespace Inspira\Collection;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Inspira\Collection\Enums\Type;
use Inspira\Collection\Exceptions\InvalidLiteralTypeException;
use Inspira\Collection\Exceptions\InvalidTypeException;
use Inspira\Collection\Exceptions\ItemNotFoundException;
use Inspira\Contracts\Arrayable;
use IteratorAggregate;
use Traversable;

class GenericCollection implements IteratorAggregate, ArrayAccess, Countable, Arrayable
{
	public function __construct(protected array $items = [], protected Type|string|int|float|bool|null $type = Type::MIXED, protected bool $isLiteralType = false)
	{
		$this->validateType();
	}

	protected function validateType()
	{
		if ($this->type === Type::MIXED || $this->isEmpty()) {
			return;
		}

		foreach ($this->items as $key => $item) {
			$expectedType = $this->getType();
			$actualType = $this->getItemType($item);

			if ($expectedType === $actualType) {
				continue;
			}

			if ($this->isLiteralType) {
				$actualType = is_object($actualType) ? get_class($actualType) : $actualType;
				throw new InvalidLiteralTypeException("Invalid item type encountered at position [$key]. Expecting literal [$expectedType], [$actualType] given.");
			}

			throw new InvalidTypeException("Invalid item type encountered at position [$key]. Expecting type [$expectedType], [$actualType] given.");
		}
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
	 * @return mixed
	 * @throws ItemNotFoundException
	 */
	public function __get(string $name)
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
}
