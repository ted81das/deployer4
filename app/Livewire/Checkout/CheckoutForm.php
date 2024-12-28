<?php

namespace App\Livewire\Checkout;

use App\Exceptions\LoginException;
use App\Exceptions\NoPaymentProvidersAvailableException;
use App\Models\User;
use App\Services\LoginManager;
use App\Services\PaymentProviders\PaymentManager;
use App\Services\UserManager;
use App\Validator\LoginValidator;
use App\Validator\RegisterValidator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CheckoutForm extends Component
{
    public $intro;

    public $name;

    public $email;

    public $password;

    public $paymentProvider;

    public $recaptcha;

    protected $paymentProviders = [];

    public function mount(string $intro = '')
    {
        $this->intro = $intro;
    }

    public function render(PaymentManager $paymentManager)
    {
        return view('livewire.checkout.checkout-form', [
            'userExists' => $this->userExists($this->email),
            'paymentProviders' => $this->getPaymentProviders($paymentManager),
        ]);
    }

    public function handleLoginOrRegistration(
        LoginValidator $loginValidator,
        RegisterValidator $registerValidator,
        UserManager $userManager,
        LoginManager $loginManager,
    ) {
        if (! auth()->check()) {
            if ($this->userExists($this->email)) {
                $this->loginUser($loginValidator, $loginManager);
            } else {
                $this->registerUser($registerValidator, $userManager);
            }
        }

        $user = auth()->user();
        if (! $user) {
            $this->redirect(route('login'));
        }

        if ($user->is_blocked) {
            auth()->logout();
            throw ValidationException::withMessages([
                'email' => __('Your account is blocked, please contact support.'),
            ]);
        }

    }

    protected function loginUser(LoginValidator $loginValidator, LoginManager $loginManager)
    {
        $fields = [
            'email' => $this->email,
            'password' => $this->password,
        ];

        if (config('app.recaptcha_enabled')) {
            $fields[recaptchaFieldName()] = $this->recaptcha;
        }

        $validator = $loginValidator->validate($fields);

        if ($validator->fails()) {
            $this->resetReCaptcha();
            throw new ValidationException($validator);
        }

        try {
            $result = $loginManager->attempt([
                'email' => $this->email,
                'password' => $this->password,
            ],  true);
        } catch (\Throwable $e) {  // usually thrown when 2FA is enabled so user need to be redirected to login page to enter 2FA code
            throw new LoginException;
        }

        if (! $result) {
            $this->resetReCaptcha();
            throw ValidationException::withMessages([
                'email' => __('Wrong email or password'),
            ]);
        }
    }

    protected function registerUser(RegisterValidator $registerValidator, UserManager $userManager)
    {
        $fields = [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ];

        if (config('app.recaptcha_enabled')) {
            $fields[recaptchaFieldName()] = $this->recaptcha;
        }

        $validator = $registerValidator->validate($fields, false);

        if ($validator->fails()) {
            $this->resetReCaptcha();
            throw new ValidationException($validator);
        }

        $user = $userManager->createUser([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ]);

        auth()->login($user);

        return $user;
    }

    protected function userExists(?string $email): bool
    {
        if ($email === null) {
            return false;
        }

        return User::where('email', $email)->exists();
    }

    protected function getPaymentProviders(PaymentManager $paymentManager)
    {
        if (count($this->paymentProviders) > 0) {
            return $this->paymentProviders;
        }

        $this->paymentProviders = $paymentManager->getActivePaymentProviders();

        if (empty($this->paymentProviders)) {
            logger()->error('No payment providers available');

            throw new NoPaymentProvidersAvailableException('No payment providers available');
        }

        if ($this->paymentProvider === null) {
            $this->paymentProvider = $this->paymentProviders[0]->getSlug();
        }

        return $this->paymentProviders;
    }

    protected function resetReCaptcha()
    {
        $this->dispatch('reset-recaptcha');
    }
}
