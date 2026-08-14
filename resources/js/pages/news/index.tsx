import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, ChevronDown, Clock3, Newspaper } from 'lucide-react';
import { index as newsIndex } from '@/routes/news';

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
};

type NewsIndexProps = {
    news: NewsItem[];
};

const dateFormatter = new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'long',
    timeStyle: 'short',
});

export default function NewsIndex({ news }: NewsIndexProps) {
    return (
        <>
            <Head title="Новости" />

            <div className="min-h-screen bg-neutral-100 text-neutral-950 dark:bg-neutral-950 dark:text-neutral-50">
                <header className="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
                    <div className="mx-auto flex max-w-5xl items-center justify-between gap-6 px-4 py-5 sm:px-6">
                        <Link
                            href={newsIndex()}
                            className="flex items-center gap-3 font-semibold"
                        >
                            <span className="grid size-10 place-items-center rounded-xl bg-red-600 text-white">
                                <Newspaper
                                    aria-hidden="true"
                                    className="size-5"
                                />
                            </span>
                            <span>
                                News Grid
                                <span className="block text-xs font-normal text-neutral-500 dark:text-neutral-400">
                                    оригинальные новости
                                </span>
                            </span>
                        </Link>

                        <span className="rounded-full bg-neutral-100 px-3 py-1 text-sm text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                            {news.length} материалов
                        </span>
                    </div>
                </header>

                <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                    <div className="mb-8 max-w-2xl">
                        <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                            Последние новости
                        </h1>
                        <p className="mt-3 text-neutral-600 dark:text-neutral-400">
                            Здесь виден результат RSS-ingestion: заголовок, лид,
                            изображение и полный текст оригинала без
                            AI-обработки.
                        </p>
                    </div>

                    {news.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-neutral-300 bg-white p-10 text-center dark:border-neutral-700 dark:bg-neutral-900">
                            <p className="font-medium">Новостей пока нет</p>
                            <p className="mt-2 text-sm text-neutral-500">
                                Запустите php artisan news:fetch и обновите
                                страницу.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-5">
                            {news.map((item) => (
                                <article
                                    key={item.id}
                                    className="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
                                >
                                    <div className="grid md:grid-cols-[18rem_1fr]">
                                        {item.image_url ? (
                                            <img
                                                src={item.image_url}
                                                alt=""
                                                loading="lazy"
                                                className="aspect-video size-full object-cover md:aspect-auto"
                                            />
                                        ) : (
                                            <div className="grid aspect-video place-items-center bg-neutral-200 text-neutral-400 md:aspect-auto dark:bg-neutral-800">
                                                <Newspaper
                                                    aria-hidden="true"
                                                    className="size-10"
                                                />
                                            </div>
                                        )}

                                        <div className="p-5 sm:p-6">
                                            <div className="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-neutral-500 dark:text-neutral-400">
                                                <span className="font-semibold tracking-wide text-red-600 uppercase dark:text-red-400">
                                                    {item.category ??
                                                        item.source_name}
                                                </span>
                                                <span className="flex items-center gap-1.5">
                                                    <Clock3
                                                        aria-hidden="true"
                                                        className="size-3.5"
                                                    />
                                                    {dateFormatter.format(
                                                        new Date(
                                                            item.published_at,
                                                        ),
                                                    )}
                                                </span>
                                                {item.author && (
                                                    <span>{item.author}</span>
                                                )}
                                            </div>

                                            <h2 className="mt-3 text-xl leading-tight font-semibold sm:text-2xl">
                                                {item.title}
                                            </h2>

                                            {item.lead && (
                                                <p className="mt-3 leading-7 text-neutral-600 dark:text-neutral-300">
                                                    {item.lead}
                                                </p>
                                            )}

                                            <details className="group mt-5 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                                                <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-neutral-700 outline-none hover:text-red-600 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-4 dark:text-neutral-200 dark:focus-visible:ring-offset-neutral-900 [&::-webkit-details-marker]:hidden">
                                                    Читать полный текст
                                                    <ChevronDown
                                                        aria-hidden="true"
                                                        className="size-4 transition-transform group-open:rotate-180"
                                                    />
                                                </summary>

                                                <div className="mt-5 space-y-4 leading-7 text-neutral-700 dark:text-neutral-300">
                                                    {item.content
                                                        .split(/\n{2,}/)
                                                        .map(
                                                            (
                                                                paragraph,
                                                                index,
                                                            ) => (
                                                                <p key={index}>
                                                                    {paragraph}
                                                                </p>
                                                            ),
                                                        )}

                                                    <a
                                                        href={item.original_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1.5 font-medium text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                                    >
                                                        Оригинал на{' '}
                                                        {item.source_name}
                                                        <ArrowUpRight
                                                            aria-hidden="true"
                                                            className="size-4"
                                                        />
                                                    </a>
                                                </div>
                                            </details>
                                        </div>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
