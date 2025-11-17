<?php
namespace App\Traits;

use Exception;

/**
 * A class handles filter, sort, limit, offset, and pagination
 */
trait QueryBuilderTrait {
    public function buildQuery($query, $request)
    {
        $this->filter($query, $request);
        $this->sort($query, $request);
        $this->limit($query, $request);
        $this->offset($query, $request);
    }

    /**
     * Filter query by filterable columns
     * @param $query
     * @param $request
     * @return void
     * @throws Exception
     */
    public function filter($query, $request)
    {
        // get Model form $query
        $model = $query->getModel();

        // get filterable and allow $request->filter filter by filerable columns
        $filterable = $model::FILTERABLE ?? [];

        if (count($filterable) == 0) {
            throw new Exception('No filterable columns found in model, please set FILTERABLE array const');
            return;
        }

        if ($request->has('filter')) {
             // intersect filterable columns with request->filter
            $filterable = array_intersect($filterable, [$request->filter]);

            $query->when($filterable, function ($query) use ($request, $filterable) {
                $query->where($filterable, 'like', '%' . $request->filter_value . '%');
            });
        }
    }

    public function sort($query, $request)
    {
        $query->when($request->has('sort'), function ($query) use ($request) {
            $query->orderBy($request->sort, $request->order ?? 'asc');
        }, function ($query) {
            $query->orderBy('created_at', 'desc');
        });
    }

    public function limit($query, $request)
    {
        $query->when($request->has('limit'), function ($query) use ($request) {
            $query->limit($request->limit);
        });
    }

    public function offset($query, $request)
    {
        $query->when($request->has('offset'), function ($query) use ($request) {
            $query->offset($request->offset);
        });
    }
}