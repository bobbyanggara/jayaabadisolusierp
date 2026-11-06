<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;

class JaspestBlogSeeder extends Seeder
{
    public function run(): void
    {
        $posts = json_decode(file_get_contents(database_path('seeders/data/jaspest-blog.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($posts as $post) {
            // Data awal tidak menimpa tulisan yang telah diedit admin.
            BlogPost::firstOrCreate(['slug' => $post['slug']], $post);
        }
    }
}
