<?php

namespace Modules\Base\Services\Access\Traits;

use Illuminate\Http\Request;
use Modules\Base\Exceptions\GeneralException;
use Modules\Base\Events\Auth\UserLoggedIn;
use Modules\Base\Events\Auth\UserLoggedOut;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Modules\Base\Http\Requests\Auth\LoginRequest;

/**
 * Class AuthenticatesUsers
 * @package App\Services\Access\Traits
 */
trait AuthenticatesUsers
{
    use RedirectsUsers;

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view('base::auth.login')
            ->withSocialiteLinks($this->getSocialLinks());
    }

    /**
     * @param LoginRequest $request
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function login(LoginRequest $request)
    {
        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        $throttles = in_array(
            ThrottlesLogins::class, class_uses_recursive(get_class($this))
        );

        if ($throttles && $this->hasTooManyLoginAttempts($request)) {
            return $this->sendLockoutResponse($request);
        }

        if (auth()->attempt($request->only($this->username(), 'password'), $request->has('remember'))) {
            return $this->handleUserWasAuthenticated($request, $throttles);
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        if ($throttles) {
            $this->incrementLoginAttempts($request);
        }

        return redirect()->back()
            ->withInput(array_merge($request->only($this->username(), 'remember')))
            ->withErrors([
                $this->username() => trans('auth.failed'),
            ]);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function logout()
    {
        /**
         * Remove the socialite session variable if exists
         */
        if (app('session')->has(config('base.socialite_session_name'))) {
            app('session')->forget(config('base.socialite_session_name'));
        }

        //access()->user()->clearApiToken();
        event(new UserLoggedOut(access()->user()));
        auth()->logout();
        return redirect(property_exists($this, 'redirectAfterLogout') ? $this->redirectAfterLogout : '/');
    }

    /**
     * This is here so we can use the default Laravel ThrottlesLogins trait
     *
     * @return string
     */
    public function username()
    {
        return 'email';
    }

    /**
     * @param Request $request
     * @param $throttles
     * @return \Illuminate\Http\RedirectResponse
     * @throws GeneralException
     */
    protected function handleUserWasAuthenticated(Request $request, $throttles)
    {
        if ($throttles) {
            $this->clearLoginAttempts($request);
        }

        /**
         * Check to see if the users account is confirmed and active
         */
        if (! access()->user()->isConfirmed()) {
            $token = access()->user()->confirmation_code;
            auth()->logout();
            throw new GeneralException(trans('exceptions.auth.confirmation.resend', ['token' => $token]));
        } elseif (! access()->user()->isActive()) {
            auth()->logout();
            throw new GeneralException(trans('exceptions.auth.deactivated'));
        }

        event(new UserLoggedIn(access()->user()));
        return redirect()->intended($this->redirectPath());
    }
}
