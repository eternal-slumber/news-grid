import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, ImageOff } from 'lucide-react';
import { useState } from 'react';
import { index as newsIndex, show as newsShow } from '@/routes/news';

type Mood = 'positive' | 'sad' | 'ironic';
type Tone = 'original' | Mood;

type NewsRewrite = {
    id: number;
    mood: Mood;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    title: string | null;
    lead: string | null;
    validation_passed: boolean | null;
};

type NewsItem = {
    id: number;
    source_name: string;
    author: string | null;
    category: string | null;
    title: string;
    lead: string | null;
    image_url: string | null;
    published_at: string;
    rewrites: NewsRewrite[];
};

const tones: { value: Tone; label: string; hint: string }[] = [
    { value: 'sad', label: 'Грустно', hint: 'Мрачнее' },
    { value: 'original', label: 'Оригинал', hint: 'Как в источнике' },
    { value: 'positive', label: 'Позитивно', hint: 'Светлее' },
    { value: 'ironic', label: 'Иронично', hint: 'С улыбкой' },
];

const issueDateFormatter = new Intl.DateTimeFormat('ru-RU', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const storyDateFormatter = new Intl.DateTimeFormat('ru-RU', {
    day: 'numeric',
    month: 'long',
    hour: '2-digit',
    minute: '2-digit',
});

function availableRewrite(newsItem: NewsItem, mood: Mood) {
    return newsItem.rewrites.find(
        (rewrite) =>
            rewrite.mood === mood &&
            rewrite.status === 'completed' &&
            rewrite.validation_passed === true &&
            rewrite.title,
    );
}

function storyCopy(newsItem: NewsItem, tone: Tone) {
    if (tone === 'original') {
        return { title: newsItem.title, lead: newsItem.lead, isOriginal: true };
    }

    const rewrite = availableRewrite(newsItem, tone);

    return {
        title: rewrite?.title ?? newsItem.title,
        lead: rewrite?.lead ?? newsItem.lead,
        isOriginal: !rewrite,
    };
}

function StoryImage({
    newsItem,
    eager = false,
}: {
    newsItem: NewsItem;
    eager?: boolean;
}) {
    const [failed, setFailed] = useState(false);

    if (!newsItem.image_url || failed) {
        return (
            <div className="grid aspect-[16/10] place-items-center border-y border-black/15 bg-stone-200 text-stone-500 dark:border-white/15 dark:bg-stone-800 dark:text-stone-400">
                <span className="flex items-center gap-2 text-[0.65rem] font-semibold tracking-[0.16em] uppercase">
                    <ImageOff aria-hidden="true" className="size-4" />
                    Без фотографии
                </span>
            </div>
        );
    }

    return (
        <img
            src={newsItem.image_url}
            alt=""
            loading={eager ? 'eager' : 'lazy'}
            onError={() => setFailed(true)}
            className="aspect-[16/10] w-full bg-stone-200 object-cover grayscale-[12%] transition-[filter] duration-300 group-hover:grayscale-0 motion-reduce:duration-0 dark:bg-stone-800"
        />
    );
}

function StoryMeta({ newsItem }: { newsItem: NewsItem }) {
    return (
        <p className="flex flex-wrap gap-x-2 text-[0.65rem] font-semibold tracking-[0.14em] text-stone-600 uppercase dark:text-stone-400">
            <span>{newsItem.source_name}</span>
            <span aria-hidden="true">·</span>
            <time dateTime={newsItem.published_at}>
                {storyDateFormatter.format(new Date(newsItem.published_at))}
            </time>
        </p>
    );
}

function Story({
    newsItem,
    tone,
    variant = 'regular',
}: {
    newsItem: NewsItem;
    tone: Tone;
    variant?: 'lead' | 'compact' | 'regular';
}) {
    const copy = storyCopy(newsItem, tone);
    const isLead = variant === 'lead';

    return (
        <article className="group min-w-0">
            <Link
                href={newsShow(newsItem.id, { query: { tone } })}
                className="block h-full focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-red-800"
                prefetch
            >
                <div className="flex h-full flex-col gap-4">
                    <p className="text-[0.65rem] font-bold tracking-[0.18em] text-red-800 uppercase dark:text-red-400">
                        {newsItem.category ?? 'Новости'}
                    </p>

                    <div
                        key={`${newsItem.id}-${tone}`}
                        className="animate-in duration-200 fade-in motion-reduce:animate-none"
                    >
                        <h2
                            className={`font-serif font-bold text-balance decoration-1 underline-offset-4 group-hover:underline ${
                                isLead
                                    ? 'text-4xl leading-[0.96] sm:text-5xl lg:text-6xl'
                                    : variant === 'compact'
                                      ? 'text-2xl leading-[1.04]'
                                      : 'text-3xl leading-[1.02]'
                            }`}
                        >
                            {copy.title}
                        </h2>

                        {copy.lead && (
                            <p
                                className={`${isLead ? 'mt-5 max-w-2xl text-lg leading-7' : 'mt-3 line-clamp-3 leading-6'} text-stone-700 dark:text-stone-300`}
                            >
                                {copy.lead}
                            </p>
                        )}

                        {!copy.isOriginal && (
                            <p className="mt-3 text-[0.62rem] font-semibold tracking-[0.14em] text-stone-500 uppercase dark:text-stone-400">
                                Подача изменена · факты из оригинала
                            </p>
                        )}

                        {tone !== 'original' && copy.isOriginal && (
                            <p className="mt-3 text-xs text-stone-500 dark:text-stone-400">
                                Эта подача недоступна — показан оригинал
                            </p>
                        )}
                    </div>

                    {(isLead || variant === 'regular') && (
                        <StoryImage newsItem={newsItem} eager={isLead} />
                    )}

                    <div className="mt-auto flex items-end justify-between gap-4 border-t border-black/15 pt-3 dark:border-white/15">
                        <StoryMeta newsItem={newsItem} />
                        <ArrowUpRight
                            aria-hidden="true"
                            className="size-4 shrink-0 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transform-none"
                        />
                    </div>
                </div>
            </Link>
        </article>
    );
}

function ToneSwitcher({
    tone,
    onChange,
}: {
    tone: Tone;
    onChange: (tone: Tone) => void;
}) {
    return (
        <section
            aria-labelledby="tone-heading"
            className="border-y border-black py-5 dark:border-white"
        >
            <div className="grid gap-5 lg:grid-cols-[15rem_1fr_15rem] lg:items-center">
                <div>
                    <p
                        id="tone-heading"
                        className="text-xs font-bold tracking-[0.18em] uppercase"
                    >
                        Температура подачи
                    </p>
                    <p className="mt-1 text-sm text-stone-600 dark:text-stone-400">
                        Мрачнее → веселее
                    </p>
                </div>

                <div
                    role="group"
                    aria-label="Выберите тон новостей"
                    className="grid grid-cols-2 border border-black sm:grid-cols-4 dark:border-white"
                >
                    {tones.map((option, index) => {
                        return (
                            <button
                                key={option.value}
                                type="button"
                                aria-pressed={tone === option.value}
                                onClick={() => onChange(option.value)}
                                className={`min-h-16 border-black px-3 py-2 text-left transition-colors focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-red-800 sm:border-l dark:border-white ${index % 2 === 1 ? 'border-l' : ''} ${index > 1 ? 'border-t sm:border-t-0' : ''} ${tone === option.value ? 'bg-red-900 text-stone-50 dark:bg-red-800' : 'hover:bg-stone-200 dark:hover:bg-stone-800'}`}
                            >
                                <span className="block text-[0.58rem] tracking-[0.15em] uppercase opacity-70">
                                    {option.hint}
                                </span>
                                <span className="mt-1 block text-xs font-bold tracking-[0.1em] uppercase">
                                    {option.label}
                                </span>
                            </button>
                        );
                    })}
                </div>

                <p className="font-serif text-lg leading-5 italic lg:text-right">
                    Факты остаются.
                    <br />
                    Меняется тон.
                </p>
            </div>
        </section>
    );
}

export default function NewsIndex({ news }: { news: NewsItem[] }) {
    const [tone, setTone] = useState<Tone>('original');
    const sources = [
        ...new Set(news.map(({ source_name }) => source_name)),
    ].join(', ');
    const [leadStory, ...otherStories] = news;

    return (
        <>
            <Head title="Новости — факты остаются, меняется тон" />

            <div className="min-h-screen bg-[#f3f0e8] text-stone-950 dark:bg-stone-950 dark:text-stone-100">
                <header className="mx-auto max-w-[90rem] px-4 pt-5 sm:px-8 lg:px-12">
                    <div className="flex items-center justify-between border-y border-black py-2 text-[0.62rem] font-semibold tracking-[0.13em] uppercase dark:border-white">
                        <time dateTime={new Date().toISOString()}>
                            {issueDateFormatter.format(new Date())}
                        </time>
                        <span className="max-w-[45%] truncate text-right">
                            Источник: {sources || 'не указан'}
                        </span>
                    </div>

                    <div className="py-7 text-center sm:py-9">
                        <Link
                            href={newsIndex()}
                            aria-label="News Grid — главная"
                            className="inline-block"
                        >
                            <span className="font-serif text-[clamp(2.65rem,10vw,8.5rem)] leading-[0.72] font-black tracking-[-0.075em]">
                                NEWSGRID
                            </span>
                        </Link>
                    </div>

                    <ToneSwitcher tone={tone} onChange={setTone} />
                </header>

                <main className="mx-auto max-w-[90rem] px-4 py-8 sm:px-8 lg:px-12 lg:py-10">
                    {news.length === 0 ? (
                        <section className="border-y border-black py-24 text-center dark:border-white">
                            <p className="font-serif text-4xl font-bold">
                                Свежих новостей пока нет.
                            </p>
                            <p className="mt-3 text-sm text-stone-600 dark:text-stone-400">
                                Следующий выпуск уже готовится.
                            </p>
                        </section>
                    ) : (
                        <>
                            <section
                                aria-label="Главные материалы"
                                className="grid border-b border-black pb-8 lg:grid-cols-12 dark:border-white"
                            >
                                <div className="pb-8 lg:col-span-8 lg:border-r lg:border-black lg:pr-8 lg:pb-0 dark:lg:border-white">
                                    <Story
                                        newsItem={leadStory}
                                        tone={tone}
                                        variant="lead"
                                    />
                                </div>

                                <div className="grid divide-y divide-black border-t border-black lg:col-span-4 lg:border-t-0 lg:pl-8 dark:divide-white dark:border-white">
                                    {otherStories
                                        .slice(0, 2)
                                        .map((newsItem) => (
                                            <div
                                                key={newsItem.id}
                                                className="py-7 first:pt-8 lg:first:pt-0"
                                            >
                                                <Story
                                                    newsItem={newsItem}
                                                    tone={tone}
                                                    variant="compact"
                                                />
                                            </div>
                                        ))}
                                </div>
                            </section>

                            {otherStories.length > 2 && (
                                <section
                                    aria-label="Остальные новости"
                                    className="grid border-l border-black sm:grid-cols-2 xl:grid-cols-3 dark:border-white"
                                >
                                    {otherStories.slice(2).map((newsItem) => (
                                        <div
                                            key={newsItem.id}
                                            className="border-r border-b border-black p-6 sm:p-7 dark:border-white"
                                        >
                                            <Story
                                                newsItem={newsItem}
                                                tone={tone}
                                            />
                                        </div>
                                    ))}
                                </section>
                            )}
                        </>
                    )}
                </main>

                <footer className="mx-auto max-w-[90rem] px-4 pb-8 sm:px-8 lg:px-12">
                    <div className="flex flex-col gap-2 border-t border-black pt-4 text-[0.62rem] font-semibold tracking-[0.14em] uppercase sm:flex-row sm:justify-between dark:border-white">
                        <span>News Grid · цифровая редакция</span>
                        <span>Оригинал всегда доступен</span>
                    </div>
                </footer>
            </div>
        </>
    );
}
