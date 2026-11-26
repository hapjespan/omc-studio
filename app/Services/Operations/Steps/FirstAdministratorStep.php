<?php

namespace App\Services\Operations\Steps;

use App\Enums\Role;
use App\Models\User;
use App\Services\Accounts\OneTimePassword;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class FirstAdministratorStep
{
    public function __construct(private readonly OneTimePassword $passwords) {}

    public function __invoke(OutputInterface $output = new NullOutput): void
    {
        if (User::query()->exists()) {
            return;
        }

        $administrator = new User([
            'name' => 'Administrator',
            'email' => (string) config('auth.first_administrator_email'),
            'role' => Role::Administrator,
        ]);

        $password = $this->passwords->issueTo($administrator);

        $output->writeln('Administrator: '.$administrator->email);
        $output->writeln('One-time password: '.$password);
    }
}
