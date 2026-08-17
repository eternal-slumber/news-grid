import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowUpRight,
    Check,
    ImageOff,
    Maximize2,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { index as newsIndex } from '@/routes/news';

type Mood = 'positive' | 'sad' | 'ironic';
type MobilePage = 'original' | 'rewrite';
type FocusedPane = MobilePage | null;

type NewsRewrite = {
    id: number;
    mood: Mood;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    title: string | null;
    lead: string | null;
    content: string | null;
    validation_passed: boolean | null;
    validation_message: string | null;
};

type NewsItem = {
    id: number;
    source_name: string;
    original_url: string;
    author: string | null;
    category: string | null;
    title: string;
    lead: string | null;
    content: string;
    image_url: string | null;
    published_at: string;
    rewrites: NewsRewrite[];
};

const moods: { value: Mood; label: string; section: string }[] = [
    { value: 'sad', label: 'Грустно', section: 'Мрачная полоса' },
    { value: 'positive', label: 'Позитивно', section: 'Хорошие вести' },
    {
        value: 'ironic',
        label: 'Иронично',
        section: 'С улыбкой о серьёзном',
    },
];

const dateFormatter = new Intl.DateTimeFormat('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

function availableRewrite(news: NewsItem, mood: Mood) {
    return news.rewrites.find(
        (rewrite) =>
            rewrite.mood === mood &&
            rewrite.status === 'completed' &&
            rewrite.validation_passed === true &&
            rewrite.title &&
            rewrite.content,
    );
}

function ArticleImage({ news }: { news: NewsItem }) {
    const [failed, setFailed] = useState(false);

    if (!news.image_url || failed) {
        return (
            <div className="my-8 grid aspect-[16/9] place-items-center border-y border-black/20 bg-stone-200 text-stone-500 dark:border-white/20 dark:bg-stone-800 dark:text-stone-400">
                <span className="flex items-center gap-2 text-xs font-semibold tracking-[0.15em] uppercase">
                    <ImageOff aria-hidden="true" className="size-4" />
                    Без фотографии
                </span>
            </div>
        );
    }

    return (
        <figure className="my-8">
            <img
                src={news.image_url}
                alt=""
                onError={() => setFailed(true)}
                className="aspect-[16/9] w-full object-cover"
            />
            <figcaption className="mt-2 text-[0.62rem] font-semibold tracking-[0.12em] text-stone-500 uppercase">
                Изображение оригинальной публикации · {news.source_name}
            </figcaption>
        </figure>
    );
}

function ArticleText({
    title,
    lead,
    content,
    primary = false,
    image,
}: {
    title: string;
    lead: string | null;
    content: string;
    primary?: boolean;
    image?: ReactNode;
}) {
    const headingClass =
        'break-words font-serif text-4xl leading-[1.02] font-bold text-balance sm:text-5xl';

    return (
        <>
            {primary ? (
                <h1 className={headingClass}>{title}</h1>
            ) : (
                <h2 className={headingClass}>{title}</h2>
            )}
            {lead && (
                <p className="mt-6 border-l-2 border-red-800 pl-5 font-serif text-xl leading-8 text-stone-700 dark:border-red-500 dark:text-stone-300">
                    {lead}
                </p>
            )}
            {image}
            <div className="mt-8 space-y-6 font-serif text-[1.08rem] leading-8 break-words text-stone-800 dark:text-stone-200">
                {content.split(/\n{2,}/).map((paragraph, index) => (
                    <p key={index}>{paragraph}</p>
                ))}
            </div>
        </>
    );
}

function ToneSwitcher({
    news,
    mood,
    onChange,
}: {
    news: NewsItem;
    mood: Mood | null;
    onChange: (mood: Mood) => void;
}) {
    return (
        <div
            role="group"
            aria-label="Выберите тон подачи"
            className="grid grid-cols-3 border border-black dark:border-white"
        >
            {moods.map((option, index) => {
                const disabled = !availableRewrite(news, option.value);

                return (
                    <button
                        key={option.value}
                        type="button"
                        disabled={disabled}
                        aria-pressed={mood === option.value}
                        onClick={() => onChange(option.value)}
                        className={`min-h-12 border-black px-3 py-2 text-xs font-bold tracking-[0.08em] uppercase transition-colors focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-red-800 disabled:cursor-not-allowed disabled:opacity-35 dark:border-white ${index > 0 ? 'border-l' : ''} ${mood === option.value ? 'bg-red-900 text-white dark:bg-red-800' : 'hover:bg-stone-200 dark:hover:bg-stone-800'}`}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}

function SectionHeader({
    label,
    accent = false,
    onExpand,
}: {
    label: string;
    accent?: boolean;
    onExpand?: () => void;
}) {
    return (
        <div className="mb-5 flex items-center justify-between gap-4">
            <p
                className={`text-[0.65rem] font-bold tracking-[0.18em] uppercase ${accent ? 'text-red-800 dark:text-red-400' : ''}`}
            >
                {label}
            </p>
            {onExpand && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            aria-label="Развернуть для чтения"
                            onClick={onExpand}
                            className="grid size-8 place-items-center border border-black/30 text-stone-600 transition-colors hover:border-black hover:text-stone-950 focus-visible:outline-2 focus-visible:outline-red-800 dark:border-white/30 dark:text-stone-400 dark:hover:border-white dark:hover:text-white"
                        >
                            <Maximize2 aria-hidden="true" className="size-4" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>Развернуть для чтения</TooltipContent>
                </Tooltip>
            )}
        </div>
    );
}

function SourceDetails({ news }: { news: NewsItem }) {
    return (
        <aside className="mt-10 border-y border-black py-5 dark:border-white">
            <p className="text-[0.62rem] font-bold tracking-[0.18em] text-red-800 uppercase dark:text-red-400">
                Источник
            </p>
            <div className="mt-3 grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                <div>
                    <p className="font-serif text-2xl font-bold">
                        {news.source_name}
                    </p>
                    <p className="mt-1 text-sm text-stone-600 dark:text-stone-400">
                        {news.author && <>{news.author} · </>}
                        <time dateTime={news.published_at}>
                            {dateFormatter.format(new Date(news.published_at))}
                        </time>
                        {news.category && <> · {news.category}</>}
                    </p>
                </div>
                <a
                    href={news.original_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-2 text-sm font-bold underline decoration-1 underline-offset-4 hover:text-red-800 dark:hover:text-red-400"
                >
                    Открыть первоисточник
                    <ArrowUpRight aria-hidden="true" className="size-4" />
                </a>
            </div>
        </aside>
    );
}

function ValidationNote({ rewrite }: { rewrite: NewsRewrite }) {
    return (
        <details className="mt-10 border-y border-black py-4 dark:border-white">
            <summary className="flex cursor-pointer list-none items-center gap-2 text-xs font-bold tracking-[0.1em] uppercase focus-visible:outline-2 focus-visible:outline-red-800 [&::-webkit-details-marker]:hidden">
                <Check
                    aria-hidden="true"
                    className="size-4 text-red-800 dark:text-red-400"
                />
                Проверено по оригиналу
            </summary>
            <p className="mt-3 max-w-xl text-sm leading-6 text-stone-600 dark:text-stone-400">
                Переписанная версия автоматически сравнена с оригинальным
                текстом на предмет изменения фактических утверждений.
                {rewrite.validation_message && (
                    <> Результат проверки: {rewrite.validation_message}</>
                )}
            </p>
        </details>
    );
}

export default function NewsShow({ news }: { news: NewsItem }) {
    const page = usePage();
    const requestedTone = new URL(
        page.url,
        'https://newsgrid.local',
    ).searchParams.get('tone');
    const requestedMood = moods.find(
        ({ value }) => value === requestedTone,
    )?.value;
    const initialMood =
        requestedMood && availableRewrite(news, requestedMood)
            ? requestedMood
            : ((['positive', 'sad', 'ironic'] as Mood[]).find((mood) =>
                  availableRewrite(news, mood),
              ) ?? null);
    const [mood, setMood] = useState<Mood | null>(initialMood);
    const [mobilePage, setMobilePage] = useState<MobilePage>(
        requestedTone === 'original' ? 'original' : 'rewrite',
    );
    const [focusedPane, setFocusedPane] = useState<FocusedPane>(null);
    const rewrite = mood ? availableRewrite(news, mood) : undefined;
    const sectionTitle = moods.find(({ value }) => value === mood)?.section;

    function changeMood(nextMood: Mood) {
        setMood(nextMood);
        setMobilePage('rewrite');
    }

    return (
        <>
            <Head title={news.title} />

            <div className="min-h-screen bg-[#f3f0e8] text-stone-950 dark:bg-stone-950 dark:text-stone-100">
                <header className="mx-auto max-w-[96rem] px-4 pt-5 sm:px-8 lg:px-12">
                    <div className="flex items-center justify-between border-y border-black py-3 dark:border-white">
                        <Link
                            href={newsIndex()}
                            className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.12em] uppercase hover:text-red-800 dark:hover:text-red-400"
                        >
                            <ArrowLeft aria-hidden="true" className="size-4" />К
                            полосе новостей
                        </Link>
                        <Link
                            href={newsIndex()}
                            className="font-serif text-2xl font-black tracking-[-0.05em] sm:text-4xl"
                        >
                            NEWSGRID
                        </Link>
                        <span className="hidden text-right text-[0.6rem] font-semibold tracking-[0.12em] uppercase sm:block">
                            Факты остаются.
                            <br />
                            Меняется тон.
                        </span>
                    </div>
                </header>

                <main className="mx-auto max-w-[96rem] px-4 py-6 sm:px-8 lg:px-12 lg:py-9">
                    <div className="mb-6 grid gap-4 border-b border-black pb-6 lg:grid-cols-[1fr_minmax(32rem,44rem)] lg:items-end dark:border-white">
                        <div>
                            <p className="text-[0.62rem] font-bold tracking-[0.18em] text-red-800 uppercase dark:text-red-400">
                                {news.category ?? 'Новости'}
                            </p>
                            <p className="mt-2 text-sm text-stone-600 dark:text-stone-400">
                                Оригинал всегда слева. Справа — только
                                проверенная подача.
                            </p>
                        </div>
                        <ToneSwitcher
                            news={news}
                            mood={mood}
                            onChange={changeMood}
                        />
                    </div>

                    {rewrite ? (
                        <>
                            {focusedPane && (
                                <div className="mx-auto mb-4 max-w-[52rem]">
                                    <button
                                        type="button"
                                        onClick={() => setFocusedPane(null)}
                                        className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.1em] uppercase underline decoration-1 underline-offset-4 hover:text-red-800 focus-visible:outline-2 focus-visible:outline-red-800 dark:hover:text-red-400"
                                    >
                                        <ArrowLeft
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                        Вернуться к сравнению
                                    </button>
                                </div>
                            )}

                            <div
                                className={`${focusedPane ? 'hidden' : 'grid'} mb-5 grid-cols-2 border border-black lg:hidden dark:border-white`}
                            >
                                <button
                                    type="button"
                                    aria-pressed={mobilePage === 'original'}
                                    onClick={() => setMobilePage('original')}
                                    className={`px-3 py-3 text-xs font-bold tracking-[0.1em] uppercase ${mobilePage === 'original' ? 'bg-stone-950 text-white dark:bg-white dark:text-stone-950' : ''}`}
                                >
                                    Оригинал
                                </button>
                                <button
                                    type="button"
                                    aria-pressed={mobilePage === 'rewrite'}
                                    onClick={() => setMobilePage('rewrite')}
                                    className={`border-l border-black px-3 py-3 text-xs font-bold tracking-[0.1em] uppercase dark:border-white ${mobilePage === 'rewrite' ? 'bg-red-900 text-white dark:bg-red-800' : ''}`}
                                >
                                    Подача
                                </button>
                            </div>

                            <article
                                className={
                                    focusedPane
                                        ? 'block'
                                        : 'grid lg:grid-cols-2 lg:divide-x lg:divide-black dark:lg:divide-white'
                                }
                            >
                                <section
                                    className={
                                        focusedPane === 'rewrite'
                                            ? 'hidden'
                                            : focusedPane === 'original'
                                              ? 'mx-auto block max-w-[52rem] py-5'
                                              : `${mobilePage === 'original' ? 'block' : 'hidden'} min-w-0 py-5 lg:block lg:pr-10`
                                    }
                                >
                                    <SectionHeader
                                        label="Оригинал"
                                        onExpand={
                                            focusedPane
                                                ? undefined
                                                : () =>
                                                      setFocusedPane('original')
                                        }
                                    />
                                    <ArticleText
                                        title={news.title}
                                        lead={news.lead}
                                        content={news.content}
                                        primary
                                        image={<ArticleImage news={news} />}
                                    />
                                    <SourceDetails news={news} />
                                </section>

                                <section
                                    className={
                                        focusedPane === 'original'
                                            ? 'hidden'
                                            : focusedPane === 'rewrite'
                                              ? 'mx-auto block max-w-[52rem] py-5'
                                              : `${mobilePage === 'rewrite' ? 'block' : 'hidden'} min-w-0 py-5 lg:block lg:pl-10`
                                    }
                                >
                                    <SectionHeader
                                        label={sectionTitle ?? 'Подача'}
                                        accent
                                        onExpand={
                                            focusedPane
                                                ? undefined
                                                : () =>
                                                      setFocusedPane('rewrite')
                                        }
                                    />
                                    <ArticleText
                                        title={rewrite.title!}
                                        lead={rewrite.lead}
                                        content={rewrite.content!}
                                    />
                                    <ValidationNote rewrite={rewrite} />
                                </section>
                            </article>
                        </>
                    ) : (
                        <article className="mx-auto max-w-[52rem] py-5">
                            <p className="mb-8 border-y border-black py-4 text-center font-serif text-2xl font-bold dark:border-white">
                                Эта подача пока недоступна.
                            </p>
                            <SectionHeader label="Оригинал" />
                            <ArticleText
                                title={news.title}
                                lead={news.lead}
                                content={news.content}
                                primary
                                image={<ArticleImage news={news} />}
                            />
                            <SourceDetails news={news} />
                        </article>
                    )}
                </main>
            </div>
        </>
    );
}
