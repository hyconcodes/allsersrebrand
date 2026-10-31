{{-- Comment images/video (30MB limit, stored like post media) --}}
@php
    $cImages = $comment->imageArray();
    $cImageUrls = array_map(fn ($img) => route('images.show', ['path' => $img]), $cImages);
@endphp
@if (! empty($cImages))
    <div class="mt-1.5 flex flex-wrap gap-1.5" @click.stop>
        @foreach ($cImages as $cImg)
            <button type="button"
                @click.stop="$dispatch('open-lightbox', { images: {{ Js::from($cImageUrls) }}, index: {{ $loop->index }} })"
                class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-800">
                <img loading="lazy" src="{{ route('images.show', ['path' => $cImg]) }}" alt="Comment image"
                    class="h-20 w-20 object-cover hover:scale-105 transition-transform duration-300">
            </button>
        @endforeach
    </div>
@endif
@if ($comment->video)
    <video src="{{ route('videos.show', ['path' => $comment->video]) }}" controls controlsList="nodownload"
        playsinline preload="metadata"
        class="mt-1.5 max-h-48 w-auto max-w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-black" @click.stop></video>
@endif
