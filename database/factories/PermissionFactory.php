<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Permission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $resource = $this->faker->randomElement(['users', 'teams', 'projects', 'tasks']);
        $action = $this->faker->randomElement(['view', 'create', 'update', 'delete', 'manage']);
        $name = "{$resource}.{$action}";

        return [
            'name' => $name,
            'display_name' => ucfirst(str_replace('.', ' ', $name)),
            'description' => "Permission to {$action} {$resource}",
            'resource' => $resource,
            'action' => $action,
        ];
    }
}