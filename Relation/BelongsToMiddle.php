<?php

namespace LandPG\Relation;

use LandPG\Builder;
use LandPG\Collection;
use LandPG\Model;

class BelongsToMiddle extends Relation
{
    protected Builder $middle;

    protected Collection $middleCol;

    protected string $ofLocalKey;

    protected string $ofForeignKey;

    function __construct(Model $model, Builder $foreign, Builder $middle, string $localKey, string $ofLocalKey, string $foreignKey, string $ofForeignKey)
    {
        $this->model        = $model;
        $this->foreign      = $foreign;
        $this->middle       = $middle;
        $this->localKey     = $localKey;
        $this->ofLocalKey   = $ofLocalKey;
        $this->foreignKey   = $foreignKey;
        $this->ofForeignKey = $ofForeignKey;
    }

    public function batch(Collection $collection)
    {
        $localKeys = $collection->one($this->localKey);
        $this->middle->where($this->ofLocalKey, 'in', $localKeys);
        $this->dev();
        $this->data = $this->foreign->select();
    }

    public function dev()
    {
        $this->middleCol = $this->middle->select([$this->ofLocalKey, $this->ofForeignKey]);
        $originKeys      = $this->middleCol->one($this->ofForeignKey);
        $this->foreign->where($this->foreignKey, 'in', $originKeys);
    }

    function fetch(Model $localModel): array
    {
        $middleKeys = [];
        $columnKeys = $this->middle->getColumnKeys();
        if (count($columnKeys)) {
            $result        = [];
            $data          = $this->data;
            /** @var Model $middleModel */
            foreach ($this->middleCol as $middleIndex => $middleModel) {
                if ($localModel->{$this->localKey} === $middleModel->{$this->ofLocalKey}) {
                    $middleKey = $middleModel->{$this->ofForeignKey};
                    for ($i = 0; $i < $data->count(); $i++) {
                        /** @var Model $foreignRow */
                        $foreignRow = $data[$i];
                        if ($middleKey === $foreignRow->{$this->foreignKey}) {
                            foreach ($columnKeys as $columnKey) {
                                $foreignRow->{$columnKey} = $middleModel->{$columnKey};
                            }
                            $result[] = $foreignRow->toArray();
                            unset($data[$i]);
                            break;
                        }
                    }
                }
            }
            return $result;
        } else {
            for ($i = 0; $i < $this->middleCol->count(); $i++) {
                $middleModel = $this->middleCol[$i];
                if ($localModel->{$this->localKey} === $middleModel->{$this->ofLocalKey}) {
                    $middleKeys[] = $middleModel->{$this->ofForeignKey};
                }
            }
            $result = [];
            /** @var Model $foreignRow */
            foreach ($this->data as $foreignRow) {
                if (in_array($foreignRow->{$this->foreignKey}, $middleKeys)) {
                    $result[] = $foreignRow->toArray();
                }
            }
            return $result;
        }
    }

    function detach($ids = null): mixed
    {
        $lk    = $this->model->{$this->model->primaryKey};
        $build = (clone $this->middle)->where($this->ofLocalKey, $lk);
        if ($ids !== null) {
            $build->where($this->ofForeignKey, $ids);
        }
        return $build->delete();
    }

    function attach($ids = [], array $fixed = []): mixed
    {
        $lk = $this->model->{$this->model->primaryKey};
        if ($ids instanceof Builder) {
            $ids->columns([
                $this->ofLocalKey   => $lk,
                $this->ofForeignKey => $this->foreignKey,
                ...$fixed
            ]);
            return (clone $this->middle)->insertMany($ids);
        } else {
            $data = [];
            foreach ($ids as $fk) {
                $data[] = [
                    $this->ofLocalKey   => $lk,
                    $this->ofForeignKey => $fk,
                    ...$fixed
                ];
            }
            return (clone $this->middle)->insertMany($data);
        }
    }

    function sync($ps = [], array $fixed = []): bool
    {
        return $this->detach() !== false && $this->attach($ps, $fixed) !== false;
    }
}