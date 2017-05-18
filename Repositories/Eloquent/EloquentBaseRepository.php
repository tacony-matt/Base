<?php namespace Modules\Base\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\App;
use Modules\Base\Entities\BaseEntity;
use Modules\Base\Entities\Traits\Filterable;
use Modules\Base\Exceptions\GeneralException;
use Modules\Base\Repositories\BaseRepository;
/**
 * Class EloquentBaseRepository
 *
 * @package Modules\Base\Repositories\Eloquent
 */
abstract class EloquentBaseRepository implements BaseRepository
{
    use Filterable;

    /**
     * @var \Illuminate\Database\Eloquent\Model An instance of the Eloquent Model
     */
    protected $model;
    protected $query;

    //TODO: DESC and ASC should be constants
    protected $sortBy = 'id';
    protected $sortOrder = "desc";

    /**
     * @param Model $model
     */
    public function __construct($model)
    {
        $this->model = $model;
        $this->sortBy = $this->model->getKeyName();
        if ($this->sortBy != 'id') {
            $this->sortOrder = "asc";
        }

        $this->sortable[] = 'id';
        $this->sortable[] = 'slug';
        $this->sortable[] = 'name';

        $this->validFilterableFields[] = 'id';
        $this->validFilterableFields[] = 'slug';
        $this->validFilterableFields[] = 'name';
        $this->validFilterableFields[] = 'active';

        if(
            env('SITE_CODE')
            and method_exists($this->model, 'properties')
            and $this->model->properties() instanceof Relation
        ) {
            $this->relationships[] = 'properties';
            $this->validFilterableFields[] = 'properties.code';
            $this->addFilter('properties.code', env('SITE_CODE'));
        }
    }

    public function query()
    {
        if ($this->query instanceof Model) {
            return $this->query;
        }
        return $this->model->query();
    }

    public function getModelName() {
        return class_basename($this->model);
    }

    public function sort($by, $order = 'asc')
    {
        if (in_array($by, $this->sortable)) {
            $this->sortBy = ($by) ?: $this->model->getKeyName();
            $this->sortOrder = ($order) ?: "asc";
        }
        return $this;
    }

    protected function filterAndSort($query)
    {
        return $this->applySortToQuery($this->applyFiltersToQuery($query));
    }

    protected function applySortToQuery($query)
    {
        return $query->orderBy($this->sortBy, $this->sortOrder);
    }

    public function syncRelationships($item, $data, $relationships = [], $new = false)
    {
        if ($item instanceof BaseEntity) {
            if (empty($relationships)) {
                if (!property_exists($this, 'relationships') || !is_array($this->relationships)) {
                    return $this;
                }
                $relationships = $this->relationships;
            }

            foreach ($relationships as $relationship) {
                if (env('SITE_CODE') != null and $relationship == 'properties') {
                    if($new and env('SITE_CODE')) {
                        $this->attachObject('properties', $item, [env('SITE_CODE')]);
                    }

                    continue;
                }

                if (isset($data[$relationship]) || (isset($data['_method']) and $data['_method'] == 'PUT')) {
                    $relationship_data = (isset($data[$relationship]) and is_array($data[$relationship])) ? $data[$relationship] : [];
                    $this->attachObject($relationship, $item, $relationship_data);
                }
            }

            return $this;
        }
        throw new GeneralException(__('products::product.error.not_found'));
    }

    /**
     * @param $object
     * @param $item
     * @param array $object_ids
     * @return $this
     */
    public function attachObject($object, $item, $object_ids = [])
    {
        $method = 'attach' . ucfirst($object);

        if (method_exists($this, $method)) {
            $this->{$method}($item, $object_ids);
        } else {
            if (method_exists($item->{$object}(), 'sync')) {
                $item->{$object}()->sync($object_ids, true);
            } else {
                $item->sync($object, $object_ids);
            }
        }

        return $this;
    }

    /**
     * @param  int $id
     * @return object
     */
    public function find($id)
    {
        if ($item = $this->filterAndSort($this->query())->find($id)) {
            return $item;
        }
        throw new GeneralException(trans('Unexpected Error: Item not found.'));
    }

    public function selected_relationships($id)
    {
        $return = [];
        $item = $this->find($id);
        foreach ($this->relationships as $relationship) {
            if (method_exists($this, 'selected_' . $relationship)) {
                $return[$relationship] = $this->{'selected_' . $relationship}($item);
            } elseif (is_callable([$item, $relationship])) {
                $return[$relationship] = $item->$relationship->pluck($this->model->{$relationship}()->getRelated()->getKeyName())->all();
            }
        }
        return $return;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all()
    {
        return $this->filterAndSort($this->query())->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listAll($name_column = 'name')
    {
        return $this->filterAndSort($this->query())->pluck($name_column, $this->model->getKeyName())->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function paginate($perPage = 100)
    {
        return $this->filterAndSort($this->query())->paginate($perPage);
    }

    /**
     * @param  mixed  $data
     * @return object
     */
    public function create($data)
    {
        $item = $this->model->create($data);
        $this->syncRelationships($item, (isset($data))?$data:[], [],true);
        return $item;
    }

    /**
     * @param $model
     * @param  array  $data
     * @return object
     */
    public function update($id, $data)
    {
        if($item = $this->find($id)) {
            $item->update($data);
            $this->syncRelationships($item, (isset($data))?$data:[]);
            return $item;
        }
        throw new GeneralException(trans('Unexpected Error: Item not found.'));
    }

    /**
     * @param  Model $model
     * @return bool
     */
    public function destroy($id)
    {
        return $this->find($id)->delete();
    }

    /**
     * Find a resource by the given slug
     *
     * @param  string $slug
     * @return object
     */
    public function findBySlug($slug)
    {
        $this->addFilter('slug', $slug);
        return $this->filterAndSort($this->query())->first();
    }

    /**
     * Return a collection of elements who's ids match
     * @param array $ids
     * @return mixed
     */
    public function findByMany(array $ids)
    {
        return $this->filterAndSort($this->query())->findMany($ids);
    }

    /**
     * Clear the cache for this Repositories' Entity
     * @return bool
     */
    public function clearCache()
    {
        return true;
    }
}
