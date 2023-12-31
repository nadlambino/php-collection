<?php

namespace Inspira\Collection\Contracts;

use Closure;

interface WhereInterface
{
	public function where(string|Closure $column, mixed $comparison, mixed $value = null): static;

	public function orWhere(string|Closure $column, mixed $comparison = null, mixed $value = null): static;

	public function whereLike(string $column, string $value): static;

	public function whereNotLike(string $column, string $value): static;

	public function orWhereLike(string $column, string $value): static;

	public function orWhereNotLike(string $column, string $value): static;

	public function whereNull(string $column): static;

	public function whereNotNull(string $column): static;

	public function orWhereNull(string $column): static;

	public function orWhereNotNull(string $column): static;

	public function whereBetween(string $column, mixed $lowerBound, mixed $upperBound): static;

	public function whereNotBetween(string $column, mixed $lowerBound, mixed $upperBound): static;

	public function orWhereBetween(string $column, mixed $lowerBound, mixed $upperBound): static;

	public function orWhereNotBetween(string $column, mixed $lowerBound, mixed $upperBound): static;

	public function whereIn(string $column, array $values): static;

	public function whereNotIn(string $column, array $values): static;

	public function orWhereIn(string $column, array $values): static;

	public function orWhereNotIn(string $column, array $values): static;
}
