{{--
    Server-rendered <head> metadata of a page payload: title, SEO tags,
    canonical link and the page's metatags. Usage: <x-lpb::head :page="$lpb" />
--}}
@props(['page'])
@php
    $seo = $page['page']['seo'] ?? [];
    $title = $seo['title'] ?? $page['page']['title'] ?? '';
    $ogTitle = $seo['og_title'] ?? $title;
    $ogDescription = $seo['og_description'] ?? $seo['description'] ?? null;
@endphp
<title>{{ $title }}</title>
@if (!empty($seo['description']))
<meta name="description" content="{{ $seo['description'] }}">
@endif
@if (!empty($seo['robots']))
<meta name="robots" content="{{ $seo['robots'] }}">
@endif
@if (!empty($seo['canonical']))
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
@endif
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $ogTitle }}">
@if ($ogDescription)
<meta property="og:description" content="{{ $ogDescription }}">
@endif
@if (!empty($seo['og_image']))
<meta property="og:image" content="{{ $seo['og_image'] }}">
<meta name="twitter:card" content="summary_large_image">
@endif
@foreach ($page['page']['metatags'] ?? [] as $tag)
@if (isset($tag['property']))
<meta property="{{ $tag['property'] }}" content="{{ $tag['content'] }}">
@else
<meta name="{{ $tag['name'] }}" content="{{ $tag['content'] }}">
@endif
@endforeach
