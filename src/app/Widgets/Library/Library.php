<?php
namespace Andmarruda\Lpb\Widgets\Library;

/**
 * The built-in widget library, registered unless
 * laravel-page-builder.widgets.library is false. Every frontend package
 * ships a render component for each of them.
 */
final class Library
{
    /** @var array<int, class-string<\Andmarruda\Lpb\Widgets\WidgetDefinition>> */
    public const DEFINITIONS = [
        Heading::class,
        Text::class,
        Image::class,
        Button::class,
        Divider::class,
        Spacer::class,
        Icon::class,
        Video::class,
        Audio::class,
        Html::class,
        Map::class,
        Alert::class,
        StarRating::class,
        Counter::class,
        ProgressBar::class,
        IconBox::class,
        ImageBox::class,
        IconList::class,
        Testimonial::class,
        Tabs::class,
        Accordion::class,
        SocialIcons::class,
        Gallery::class,
        ImageCarousel::class,
        Anchor::class,
        CallToAction::class,
        PriceTable::class,
        PriceList::class,
        FlipBox::class,
        Countdown::class,
        AnimatedHeadline::class,
        Blockquote::class,
        ShareButtons::class,
        NavMenu::class,
        Slides::class,
        TestimonialCarousel::class,
        TableOfContents::class,
        Form::class,
        CodeHighlight::class,
        Hotspot::class,
    ];
}
