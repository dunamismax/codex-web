import { Link } from "react-router";

export default function NotFoundRoute() {
  return (
    <main className="mx-auto flex min-h-screen w-full max-w-2xl flex-col items-center justify-center gap-6 px-6">
      <p className="font-mono text-xs uppercase tracking-[0.24em] text-zinc-400">404</p>
      <h1 className="font-mono text-3xl">Page not found</h1>
      <Link
        className="rounded-md border border-zinc-700 px-4 py-2 text-sm hover:bg-zinc-900"
        to="/"
      >
        Back to console
      </Link>
    </main>
  );
}
