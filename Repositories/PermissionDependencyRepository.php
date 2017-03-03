<?php

namespace Modules\Base\Repositories;

/**
 * Interface PermissionDependencyRepositoryContract
 * @package Modules\Base\Repositories\Permission\Dependency
 */
interface PermissionDependencyRepository
{
    /**
     * @param  $permission_id
     * @param  $dependency_id
     * @return mixed
     */
    public function create($permission_id, $dependency_id);

    /**
     * @param  $permission_id
     * @return mixed
     */
    public function clear($permission_id);
}
