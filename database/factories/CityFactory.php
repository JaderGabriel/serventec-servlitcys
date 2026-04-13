<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ufs = ['SP', 'RJ', 'MG', 'RS', 'PR', 'BA', 'PE', 'CE'];

        return [
            'name' => fake()->city(),
            'uf' => fake()->randomElement($ufs),
            'country' => 'Brasil',
            'db_driver' => City::DRIVER_MYSQL,
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_database' => 'test_city_db',
            'db_username' => 'root',
            'db_password' => '',
            'is_active' => true,
        ];
    }
}
