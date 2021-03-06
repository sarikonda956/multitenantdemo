<?php
namespace App\Infrastructure\Media;

use Illuminate\Database\Eloquent\Builder;

trait SortableTrait
{
    public function setHighestOrderNumber()
    {
        $orderColumnName = $this->determineOrderColumnName();

        $this->$orderColumnName = $this->getHighestOrderNumber() + 1;
    }

    public function getHighestOrderNumber(): int
    {
        return (int) static::max($this->determineOrderColumnName());
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy($this->determineOrderColumnName());
    }

    /**
     * This function reorders the records: the record with the first id in the array
     * will get order 1, the record with the second it will get order 2, ...
     *
     * A starting order number can be optionally supplied (defaults to 1).
     *
     * @param array $ids
     * @param int   $startOrder
     */
    public static function setNewOrder(array $ids, int $startOrder = 1)
    {
        foreach ($ids as $id) {
            $model = static::find($id);

            $orderColumnName = $model->determineOrderColumnName();

            $model->$orderColumnName = $startOrder++;
            $model->save();
        }
    }

    /**
     * Determine the column name of the order column.
     *
     * @return string
     */
    protected function determineOrderColumnName(): string
    {
        return $this->sortable['order_column_name'] ?? 'order_column';
    }

    /*
     * Determine if the order column should be set when saving a new model instance.
     */
    public function shouldSortWhenCreating(): bool
    {
        return $this->sortable['sort_when_creating'] ?? true;
    }
}
