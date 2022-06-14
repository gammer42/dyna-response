<?php

namespace Gammer42\DynaResponse;

// use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Request;
trait DynaResponseTrait
{
    protected $data_per_page = 20;
    protected $select_columns = ['*'];
    protected $with_columns = [];
    protected $with = [];
    protected $order_by_column = 'created_at';
    protected $order_by_value = 'DESC';
    protected $pointer = null;
    protected $query_array = [];
    protected $response_code = 200;

    public function __construct(Request $request)
    {
        if (isset($request->per_page)) {
            $this->data_per_page = $request->per_page;
        }

        if (isset($request->select_columns)) {
            $this->select_columns = explode(',', $request->select_columns);
        }

        if (isset($request->with_columns)) {
            $this->getDynamicRelationalData($request->with_columns);
        }

        if (isset($request->with)) {
            $this->select_columns = $this->getSelected(json_decode($request->with, true));
            $this->with_columns = $this->getWithData(json_decode($request->with, true));
        }

        if (isset($request->order_by_column)) {
            $this->order_by_column = $request->order_by_column;
        }

        if (isset($request->order_by_value)) {
            $this->order_by_value = $request->order_by_value;
        }

        if (isset($request->pointer)) {
            $this->pointer = $request->pointer;
        }

        if (isset($request->query_array)) {
            $this->query_array = $request->query_array;
        }
    }

    public function getSelected($data)
    {
        $selected_fields = array_filter($data, function ($field) {
            return is_string($field);
        });

        if (!$selected_fields || count($selected_fields) == 0) {
            $selected_fields = ['*'];
        }
        return $selected_fields;
    }

    public function getRelationData($data)
    {
        $relational_fields = array_filter($data, function ($field) {
            return !is_string($field);
        });

        return $relational_fields;
    }

    public function getWithData($with_columns)
    {
        $with_array = [];
        $relational_fields = $this->getRelationData($with_columns);
        foreach ($relational_fields as $key => $value) {
            if (gettype($value) == 'array') {
                foreach ($value as $key2 => $value2) {
                    //     $with_array[$key][$key2] = function ($query) {
                    //         $query->select($this->select_columns);
                    //     };
                    // }
                    $with_array[$key2] = function ($query) use ($value2) {
                        $relation = $this->getRelationData($value2);

                        $query->with($this->getWithData($relation));
                        $query->select($this->getSelected($value2));
                    };
                }
            } else {
                $with_array[$key] = function ($query) use ($value) {
                    $relation = $this->getRelationData($value);

                    $query->with($this->getWithData($relation));
                    $query->select($this->getSelected($value));
                };
            }
        }
        return $with_array;
    }

    public function getDynamicRelationalData($with_column_data)
    {
        $with_column_data = explode('|', $with_column_data);
        foreach ($with_column_data as $with_column) {
            if (strpos($with_column, '>') !== false) {
                $this->getNestedRelationalData($with_column);
            } else {
                $relation = explode(':', $with_column);
                $this->with_columns[$relation[0]] = $this->getRelationalData($relation[1]);
            }
        }
        return null;
    }

    public function getRelationalData($relation_column = '', $nested_relation = [])
    {
        return function ($relation_eloquent) use ($relation_column, $nested_relation) {
            if ($relation_column !== '') {
                if (str_contains($relation_column, ',')) {
                    $select = explode(',', $relation_column);
                    $relation_eloquent->select($select);
                } else {
                    $relation_eloquent->select($relation_column);
                }
            }
            if (count($nested_relation) > 0) {
                foreach (explode('.', $nested_relation[1]) as $nested_child) {
                    $nested_array = explode(':', $nested_child);
                    if (count($nested_array) > 1) {
                        $relation_eloquent->with($nested_array[0], $this->getRelationalData($nested_array[1]));
                    } else {
                        $relation_eloquent->with($nested_array[0]);
                    }
                }
            }
        };
    }

    public function getNestedRelationalData($data)
    {
        $nested_relation = explode('>', $data);
        $nested_parent = explode(':', $nested_relation[0]);
        if (count($nested_parent) > 1) {
            $this->with_columns[$nested_parent[0]] = $this->getRelationalData($nested_parent[1], $nested_relation);
        } else {
            $this->with_columns[$nested_parent[0]] = $this->getRelationalData('', $nested_parent[1]);
        }
    }

    public function onPaginate($eloquent)
    {
        if(gettype($this->order_by_column) == 'array'){
            // dd($this->order_by_column);
            foreach ($this->order_by_column as $key => $order_by) {
                $eloquent->orderBy(json_decode($order_by, true)[0], json_decode($order_by, true)[1]);
            }
        } else {
            $eloquent->orderBy($this->order_by_column, $this->order_by_value);
        }

        return $eloquent->paginate($this->data_per_page, $this->select_columns)->withQueryString();
    }

    /**
     * Encode array from latin1 to utf8 recursively
     * @param $dat
     * @return array|string
     */
    
    /* public static function convert_from_latin1_to_utf8_recursively($dat)
    {
        if (is_string($dat)) {
            return utf8_encode($dat);
        } elseif (is_array($dat)) {
            $ret = [];
            foreach ($dat as $i => $d) $ret[$i] = self::convert_from_latin1_to_utf8_recursively($d);

            return $ret;
        } elseif (is_object($dat)) {
            foreach ($dat as $i => $d) $dat->$i = self::convert_from_latin1_to_utf8_recursively($d);

            return $dat;
        } else {
            return $dat;
        }
    } */

    /* public function onAdminSearchQuery($eloquent, $searchText)
    {
        return $eloquent->whereLike($this->query_array, $searchText);
    } */

    /* public function createNewImage($image, $destination = null, $key = null)
    {
        $imageName = $image->getClientOriginalName();
        $imageManager = new ImageManager();
        $image = $imageManager->make($image->path());

        switch ($key) {
            case 'tn':
                $image->resize(150, null, function ($constraint) {
                    $constraint->aspectRatio();
                });
                break;
            case 'full':
                $image->resize(800, null, function ($constraint) {
                    $constraint->aspectRatio();
                });
                break;
            case 'line':
                $image->resize(800, null, function ($constraint) {
                    $constraint->aspectRatio();
                });
                break;
            default:
                # code...
                break;
        }

        if ($destination == null) {
            if ($key == null) {
                $image->save(storage_path('app/public/') . $imageName);
            } else {
                $image->save(storage_path('app/public/') . $key . '/' . $imageName);
            }
        } else {
            if ($key == null) {
                Storage::disk('public')->makeDirectory($destination);
                $image->save(storage_path('app/public/') . $destination . '/' . $imageName);
            } else {
                Storage::disk('public')->makeDirectory($destination . '/' . $key);
                $image->save(storage_path('app/public/') . $destination . '/' . $key . '/' . $imageName);
            }
        }
        return $imageName;
    } */

    public function toRawSql($eloquent)
    {
        return array_reduce($eloquent->getBindings(), function ($sql, $binding) {
            return preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sql, 1);
        }, $eloquent->toSql());
    }
}
