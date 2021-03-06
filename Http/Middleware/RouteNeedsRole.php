<?php

namespace Modules\Base\Http\Middleware;

use Closure;

/**
 * Class RouteNeedsRole.
 */
class RouteNeedsRole
{
    /**
     * @param $request
     * @param Closure $next
     * @param $role
     * @param bool $needsAll
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $role, $needsAll = false)
    {
        /*
         * Roles array
         */
        if (strpos($role, ';') !== false) {
            $roles = explode(';', $role);
            $access = access()->hasRoles($roles, ($needsAll === 'true' ? true : false));
        } else {
            /**
             * Single role.
             */
            $access = access()->hasRole($role);
        }

        if (! $access) {
            if ($request->ajax() || $request->wantsJson()) {
                return response('Unauthorized.', 401);
            } else {
                flash('error' . trans('auth.general_error'));
                return redirect('/'); //->route('index');
            }
        }

        return $next($request);
    }
}
