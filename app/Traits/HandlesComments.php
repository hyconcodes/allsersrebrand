<?php

namespace App\Traits;

use App\Models\Comment;
use Livewire\WithFileUploads;

/**
 * Shared comment/reply behaviour for post surfaces (feed inline comments,
 * post detail drawer, full post page). Provides text + image/video comments,
 * replies, and author edit/delete.
 *
 * Components using this trait must expose the scoped post via $this->post
 * and refresh their own state after mutations (see addComment/updateComment/
 * deleteComment overrides below — each component keeps its own refresh).
 */
trait HandlesComments
{
    use WithFileUploads;

    public string $commentContent = '';

    public ?int $replyToId = null;

    public string $replyToName = '';

    public array $commentImages = [];

    public $commentVideo = null;

    public ?int $editingCommentId = null;

    public string $editingCommentContent = '';

    public function setReplyTo($commentId)
    {
        $comment = Comment::with('user')->find($commentId);

        if (! $comment) {
            return;
        }

        $this->replyToId = $comment->id;
        $this->replyToName = $comment->user->name;
    }

    public function cancelReply()
    {
        $this->replyToId = null;
        $this->replyToName = '';
    }

    public function removeCommentImage($index)
    {
        unset($this->commentImages[$index]);
        $this->commentImages = array_values($this->commentImages);
    }

    public function removeCommentVideo()
    {
        $this->commentVideo = null;
    }

    public function startCommentEdit($commentId)
    {
        $comment = Comment::find($commentId);

        if (! $comment || $comment->user_id !== auth()->id()) {
            $this->dispatch('toast', type: 'error', title: 'Unauthorized', message: 'You can only edit your own comments.');

            return;
        }

        $this->editingCommentId = $comment->id;
        $this->editingCommentContent = $comment->content;
    }

    public function cancelCommentEdit()
    {
        $this->editingCommentId = null;
        $this->editingCommentContent = '';
    }

    /**
     * Validate a new comment/reply (text and/or media).
     */
    protected function validateCommentInput(): array
    {
        return $this->validate([
            'commentContent' => 'nullable|string|max:1000',
            'commentImages' => 'nullable|array|max:4',
            'commentImages.*' => 'nullable|image|max:30720',
            'commentVideo' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:30720',
        ], [
            'commentImages.*.max' => __('Each image must be 30 MB or less.'),
            'commentVideo.max' => __('The video must be 30 MB or less.'),
        ]);
    }

    /**
     * Toast a friendly warning when comment attachments exceed the limit.
     */
    protected function toastCommentOversize(\Illuminate\Validation\ValidationException $e): void
    {
        foreach ($e->validator->failed() as $field => $rules) {
            if ((str_starts_with($field, 'commentImages') || $field === 'commentVideo') && isset($rules['Max'])) {
                $this->dispatch('toast', type: 'warning', title: 'File Too Large', message: 'Comment images and videos must be 30MB or less.');
                break;
            }
        }
    }

    /**
     * Store validated comment media. Returns ['images' => ?string, 'video' => ?string].
     */
    protected function storeCommentMedia(array $validated): array
    {
        $images = null;
        $video = null;

        if (! empty($validated['commentImages'])) {
            $paths = [];
            foreach ($validated['commentImages'] as $image) {
                $paths[] = $image->store('comments/images', 'public');
            }
            $images = implode(',', $paths);
        }

        if (! empty($validated['commentVideo'])) {
            $video = $validated['commentVideo']->store('comments/videos', 'public');
        }

        return ['images' => $images, 'video' => $video];
    }

    /**
     * Send reply/comment notifications for a freshly created comment.
     */
    protected function notifyForComment(Comment $comment, $post, $user): void
    {
        if ($comment->parent_id) {
            $parentComment = Comment::with('user')->find($comment->parent_id);
            if ($parentComment && $parentComment->user_id !== $user->id) {
                $parentComment->user->notify(new \App\Notifications\NewReply($parentComment, $user, $comment));
            }
        } elseif ($post->user_id !== $user->id) {
            $post->user->notify(new \App\Notifications\CommentAdded($post, $user, $comment));
        }
    }

    /**
     * Reset the composer (text, reply target, attachments, edit state).
     */
    protected function resetCommentComposer(): void
    {
        $this->reset(['commentContent', 'commentImages', 'commentVideo']);
        $this->replyToId = null;
        $this->replyToName = '';
        $this->cancelCommentEdit();
    }
}
