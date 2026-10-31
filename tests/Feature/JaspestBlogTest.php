<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JaspestBlogTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Panduan konsultasi JASPEST', 'slug' => 'panduan-konsultasi',
            'category' => 'Konsultasi', 'excerpt' => 'Informasi untuk menyiapkan konsultasi.',
            'body' => 'Siapkan informasi lokasi dan ceritakan kebutuhan rumah atau bisnis Anda kepada tim JASPEST.',
            'author_name' => 'Tim JASPEST', 'status' => 'draft',
        ], $overrides);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        Gate::define('manage-blog', fn (User $candidate): bool => $candidate->is($user));
        $this->actingAs($user);

        return $user;
    }

    public function test_cms_requires_a_signed_in_authorized_editor(): void
    {
        $this->get('/admin/blog')->assertRedirect('/admin/login');
        $user = User::factory()->create();
        Gate::define('manage-blog', fn (): bool => false);
        $this->actingAs($user)->get('/admin/blog')->assertForbidden();
        $this->post('/admin/blog', $this->data())->assertForbidden();
        $this->assertDatabaseCount('blog_posts', 0);
    }

    public function test_editor_can_save_and_edit_a_draft_without_publishing_it(): void
    {
        $this->editor();
        $this->post('/admin/blog', $this->data())->assertRedirect();
        $post = BlogPost::firstOrFail();
        $this->put('/admin/blog/'.$post->id, $this->data(['title' => 'Judul diperbarui']))->assertRedirect();
        $this->assertDatabaseHas('blog_posts', ['id' => $post->id, 'title' => 'Judul diperbarui', 'status' => 'draft']);
        $this->get('/blog/'.$post->slug)->assertNotFound();
        $this->get('/blog')->assertDontSee('Judul diperbarui');
        $this->get('/sitemap.xml')->assertDontSee('/blog/'.$post->slug);
    }

    public function test_editor_can_publish_and_then_withdraw_a_post(): void
    {
        $this->editor();
        $this->post('/admin/blog', $this->data(['status' => 'published']))->assertRedirect();
        $post = BlogPost::firstOrFail();
        $this->get('/blog/'.$post->slug)->assertOk()->assertSee('Panduan konsultasi JASPEST');
        $this->get('/blog')->assertSee('Panduan konsultasi JASPEST');
        $this->get('/sitemap.xml')->assertSee('/blog/'.$post->slug);
        $this->put('/admin/blog/'.$post->id, $this->data(['status' => 'draft']))->assertRedirect();
        $this->get('/blog/'.$post->slug)->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('/blog/'.$post->slug);
    }

    public function test_future_post_is_private_until_its_publication_time(): void
    {
        $scheduled = now()->addDay();
        $post = BlogPost::create($this->data(['status' => 'published', 'published_at' => $scheduled]));
        $this->get('/blog/'.$post->slug)->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('/blog/'.$post->slug);
        $this->travelTo($scheduled->copy()->addMinute());
        $this->get('/blog/'.$post->slug)->assertOk();
        $this->travelBack();
    }

    public function test_post_content_and_metadata_cannot_inject_html(): void
    {
        $post = BlogPost::create($this->data([
            'status' => 'published', 'published_at' => now(),
            'body' => 'Dokumentasi lokasi <script>alert("uji")</script> hanya berupa teks.',
            'meta_title' => '</title><script>alert("uji")</script>',
        ]));
        $this->get('/blog/'.$post->slug)->assertOk()
            ->assertDontSee('<script>alert(', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_slug_collision_and_non_image_upload_are_rejected(): void
    {
        $this->editor();
        BlogPost::create($this->data());
        $this->post('/admin/blog', $this->data())->assertSessionHasErrors('slug');
        Storage::fake('public');
        $this->post('/admin/blog', $this->data([
            'slug' => 'tulisan-lain', 'cover' => UploadedFile::fake()->create('bukan-foto.jpg', 1, 'text/plain'),
        ]))->assertSessionHasErrors('cover');
        $this->assertDatabaseCount('blog_posts', 1);
    }
}
