<?php

namespace Database\Factories;

use App\Models\ReportCommentTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportCommentTemplate>
 */
class ReportCommentTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
