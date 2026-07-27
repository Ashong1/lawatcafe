<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetUserPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:reset-password {email? : The email address of the user} {password? : The new password to set}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset a user password directly from the command line';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Enter user email address');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("No user found with email: {$email}");
            return Command::FAILURE;
        }

        $password = $this->argument('password') ?? $this->secret('Enter new password');

        if (empty($password)) {
            $this->error('Password cannot be empty.');
            return Command::FAILURE;
        }

        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();

        $this->info("Successfully reset password for user: {$user->name} ({$user->email})");

        return Command::SUCCESS;
    }
}
