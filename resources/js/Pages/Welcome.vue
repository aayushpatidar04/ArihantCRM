<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Bot,
    CheckCircle2,
    ChevronDown,
    MessageSquare,
    Network,
    ShieldCheck,
    Sparkles,
    Users,
    Zap,
} from 'lucide-vue-next';

const showContent = ref(false);
const scrolled = ref(false);

const handleScroll = () => {
    scrolled.value = window.scrollY > 30;
};

onMounted(() => {
    requestAnimationFrame(() => {
        showContent.value = true;
    });

    window.addEventListener('scroll', handleScroll);
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <Head title="Arihant CRM" />

    <div
        class="min-h-screen overflow-x-hidden bg-[#050816]
               text-white selection:bg-blue-500/30"
    >
        <!-- =========================================================
             HERO
        ========================================================== -->

        <section
            class="relative min-h-screen overflow-hidden"
        >
            <!-- Background Video -->
            <video
                autoplay
                muted
                loop
                playsinline
                class="absolute inset-0 h-full w-full
                       object-cover"
            >
                <!--
                    IMPORTANT:
                    Replace this path with the same background
                    video that you used in Screen 1 of Figma.
                -->
                <source
                    src="/assets/videos/event-bg.mp4"
                    type="video/mp4"
                />
            </video>

            <!-- Video Overlay -->
            <div
                class="absolute inset-0
                       bg-[#050816]/75"
            ></div>

            <!-- Gradient Overlay -->
            <div
                class="absolute inset-0
                       bg-[radial-gradient(
                           circle_at_50%_30%,
                           rgba(37,99,235,0.22),
                           transparent_42%
                       )]"
            ></div>

            <div
                class="absolute inset-0
                       bg-[linear-gradient(
                           180deg,
                           rgba(5,8,22,0.35)_0%,
                           rgba(5,8,22,0.55)_55%,
                           #050816_100%
                       )]"
            ></div>

            <!-- Animated Glow -->
            <div
                class="pointer-events-none absolute
                       -left-32 top-20 h-96 w-96
                       rounded-full
                       bg-blue-600/20
                       blur-3xl
                       animate-pulse"
            ></div>

            <div
                class="pointer-events-none absolute
                       -right-32 top-1/3 h-96 w-96
                       rounded-full
                       bg-indigo-500/20
                       blur-3xl
                       animate-pulse"
                style="animation-delay: 1.5s;"
            ></div>

            <!-- =====================================================
                 NAVBAR
            ====================================================== -->

            <header
                class="fixed left-0 right-0 top-0 z-50
                       transition-all duration-500"
                :class="
                    scrolled
                        ? 'border-b border-white/10 bg-[#050816]/80 backdrop-blur-xl'
                        : 'bg-transparent'
                "
            >
                <div
                    class="mx-auto flex h-20 max-w-7xl
                           items-center justify-between
                           px-5 sm:px-8 lg:px-10"
                >
                    <!-- Logos -->
                    <div class="flex items-center gap-4">
                        <!-- InTouch Logo -->
                        <div
                            class="flex h-10 items-center
                                   rounded-lg bg-white
                                   px-3 shadow-lg shadow-black/10"
                        >
                            <img
                                src="/assets/images/intouch.jpg"
                                alt="InTouch Software Solutions"
                                class="max-h-7 w-auto object-contain"
                            />
                        </div>

                        <!-- Divider -->
                        <div
                            class="hidden h-7 w-px
                                   bg-white/20 sm:block"
                        ></div>

                        <!-- Arihant Logo -->
                        <div
                            class="flex h-10 items-center
                                   rounded-lg bg-white/95
                                   px-3 shadow-lg shadow-black/10"
                        >
                            <img
                                src="/assets/images/logo.png"
                                alt="Arihant Capital"
                                class="max-h-7 w-auto object-contain"
                            />
                        </div>
                    </div>

                    <!-- Login -->
                    <Link
                        href="/login"
                        class="group inline-flex
                               items-center gap-2
                               rounded-xl
                               border border-white/20
                               bg-white/10
                               px-4 py-2.5
                               text-sm font-semibold
                               text-white
                               backdrop-blur-md
                               transition-all duration-300
                               hover:border-white/30
                               hover:bg-white
                               hover:text-slate-900
                               hover:shadow-xl
                               hover:shadow-blue-900/20"
                    >
                        Login

                        <ArrowRight
                            class="h-4 w-4
                                   transition-transform
                                   duration-300
                                   group-hover:translate-x-1"
                        />
                    </Link>
                </div>
            </header>

            <!-- =====================================================
                 HERO CONTENT
            ====================================================== -->

            <div
                class="relative z-10 mx-auto flex
                       min-h-screen max-w-7xl
                       items-center px-5 pb-20 pt-32
                       sm:px-8 lg:px-10"
            >
                <div
                    class="w-full max-w-4xl
                           transition-all duration-1000"
                    :class="
                        showContent
                            ? 'translate-y-0 opacity-100'
                            : 'translate-y-8 opacity-0'
                    "
                >
                    <!-- Eyebrow -->
                    <div
                        class="mb-7 inline-flex items-center
                               gap-2 rounded-full
                               border border-blue-400/20
                               bg-blue-500/10
                               px-4 py-2
                               text-xs font-semibold
                               uppercase tracking-[0.18em]
                               text-blue-200
                               backdrop-blur-md"
                    >
                        <span
                            class="relative flex h-2 w-2"
                        >
                            <span
                                class="absolute inline-flex
                                       h-full w-full
                                       animate-ping
                                       rounded-full
                                       bg-blue-400
                                       opacity-75"
                            ></span>

                            <span
                                class="relative inline-flex
                                       h-2 w-2
                                       rounded-full
                                       bg-blue-400"
                            ></span>
                        </span>

                        Intelligent Customer Communication
                    </div>

                    <!-- Main Heading -->
                    <h1
                        class="max-w-4xl text-4xl
                               font-bold leading-[1.05]
                               tracking-tight
                               sm:text-5xl
                               lg:text-7xl"
                    >
                        One powerful platform for
                        <span
                            class="relative whitespace-nowrap"
                        >
                            <span
                                class="relative z-10
                                       bg-gradient-to-r
                                       from-blue-400
                                       via-cyan-300
                                       to-indigo-400
                                       bg-clip-text
                                       text-transparent"
                            >
                                smarter conversations.
                            </span>

                            <span
                                class="absolute -bottom-1
                                       left-0 right-0
                                       h-3
                                       bg-blue-500/20
                                       blur-xl"
                            ></span>
                        </span>
                    </h1>

                    <!-- Description -->
                    <p
                        class="mt-7 max-w-2xl
                               text-base leading-7
                               text-slate-300
                               sm:text-lg"
                    >
                        Manage WhatsApp conversations, customers,
                        teams and executives from one intelligent
                        workspace — built for seamless customer
                        communication and operational efficiency.
                    </p>

                    <!-- CTA -->
                    <div
                        class="mt-9 flex flex-col
                               gap-3 sm:flex-row"
                    >
                        <Link
                            href="/login"
                            class="group inline-flex
                                   items-center
                                   justify-center
                                   gap-2
                                   rounded-xl
                                   bg-gradient-to-r
                                   from-blue-600
                                   to-indigo-600
                                   px-6 py-3.5
                                   text-sm font-semibold
                                   text-white
                                   shadow-lg
                                   shadow-blue-900/30
                                   transition-all duration-300
                                   hover:-translate-y-0.5
                                   hover:from-blue-500
                                   hover:to-indigo-500
                                   hover:shadow-xl
                                   hover:shadow-blue-900/40"
                        >
                            Login to Dashboard

                            <ArrowRight
                                class="h-4 w-4
                                       transition-transform
                                       duration-300
                                       group-hover:translate-x-1"
                            />
                        </Link>

                        <a
                            href="#features"
                            class="inline-flex
                                   items-center
                                   justify-center
                                   gap-2
                                   rounded-xl
                                   border border-white/15
                                   bg-white/5
                                   px-6 py-3.5
                                   text-sm font-semibold
                                   text-slate-200
                                   backdrop-blur-md
                                   transition-all duration-300
                                   hover:bg-white/10
                                   hover:text-white"
                        >
                            Explore Platform

                            <ChevronDown
                                class="h-4 w-4"
                            />
                        </a>
                    </div>

                    <!-- Trust / Ownership -->
                    <div
                        class="mt-10 flex flex-wrap
                               items-center gap-x-6
                               gap-y-3 text-xs
                               text-slate-400"
                    >
                        <div
                            class="flex items-center gap-2"
                        >
                            <CheckCircle2
                                class="h-4 w-4 text-blue-400"
                            />

                            Secure communication
                        </div>

                        <div
                            class="flex items-center gap-2"
                        >
                            <CheckCircle2
                                class="h-4 w-4 text-blue-400"
                            />

                            Centralized CRM
                        </div>

                        <div
                            class="flex items-center gap-2"
                        >
                            <CheckCircle2
                                class="h-4 w-4 text-blue-400"
                            />

                            Team-based operations
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scroll Indicator -->
            <a
                href="#features"
                class="absolute bottom-7
                       left-1/2 z-20
                       flex -translate-x-1/2
                       flex-col items-center
                       gap-2 text-slate-400
                       transition hover:text-white"
            >
                <span
                    class="text-[10px] uppercase
                           tracking-[0.25em]"
                >
                    Explore
                </span>

                <ChevronDown
                    class="h-4 w-4 animate-bounce"
                />
            </a>
        </section>

        <!-- =========================================================
             FEATURES
        ========================================================== -->

        <section
            id="features"
            class="relative overflow-hidden
                   bg-[#050816] py-24 sm:py-28"
        >
            <!-- Background glow -->
            <div
                class="pointer-events-none absolute
                       left-1/2 top-0
                       h-[500px] w-[800px]
                       -translate-x-1/2
                       rounded-full
                       bg-blue-600/10
                       blur-3xl"
            ></div>

            <div
                class="relative mx-auto max-w-7xl
                       px-5 sm:px-8 lg:px-10"
            >
                <!-- Section heading -->
                <div
                    class="mx-auto max-w-2xl
                           text-center"
                >
                    <div
                        class="mb-4 inline-flex
                               items-center gap-2
                               rounded-full
                               border border-white/10
                               bg-white/5
                               px-3.5 py-1.5
                               text-xs font-medium
                               text-blue-300"
                    >
                        <Sparkles
                            class="h-3.5 w-3.5"
                        />

                        Built for modern teams
                    </div>

                    <h2
                        class="text-3xl font-bold
                               tracking-tight
                               text-white
                               sm:text-4xl"
                    >
                        Everything your team needs
                        to communicate better.
                    </h2>

                    <p
                        class="mt-4 text-sm leading-6
                               text-slate-400 sm:text-base"
                    >
                        A unified workspace designed to bring
                        customer conversations, people and
                        business processes together.
                    </p>
                </div>

                <!-- Feature Grid -->
                <div
                    class="mt-14 grid gap-5
                           sm:grid-cols-2
                           lg:grid-cols-3"
                >
                    <!-- Card 1 -->
                    <div
                        class="group relative overflow-hidden
                               rounded-2xl
                               border border-white/10
                               bg-white/[0.035]
                               p-6
                               backdrop-blur-sm
                               transition-all duration-500
                               hover:-translate-y-1
                               hover:border-blue-400/20
                               hover:bg-white/[0.055]"
                    >
                        <div
                            class="mb-6 flex h-11 w-11
                                   items-center justify-center
                                   rounded-xl
                                   bg-blue-500/10
                                   text-blue-400
                                   ring-1 ring-blue-400/20"
                        >
                            <MessageSquare
                                class="h-5 w-5"
                            />
                        </div>

                        <h3
                            class="text-base font-semibold
                                   text-white"
                        >
                            Unified Conversations
                        </h3>

                        <p
                            class="mt-2 text-sm leading-6
                                   text-slate-400"
                        >
                            Manage customer conversations
                            through a centralized communication
                            workspace.
                        </p>

                        <div
                            class="absolute -right-12
                                   -top-12 h-28 w-28
                                   rounded-full
                                   bg-blue-500/10
                                   blur-2xl
                                   transition group-hover:bg-blue-500/20"
                        ></div>
                    </div>

                    <!-- Card 2 -->
                    <div
                        class="group relative overflow-hidden
                               rounded-2xl
                               border border-white/10
                               bg-white/[0.035]
                               p-6
                               backdrop-blur-sm
                               transition-all duration-500
                               hover:-translate-y-1
                               hover:border-indigo-400/20
                               hover:bg-white/[0.055]"
                    >
                        <div
                            class="mb-6 flex h-11 w-11
                                   items-center justify-center
                                   rounded-xl
                                   bg-indigo-500/10
                                   text-indigo-400
                                   ring-1 ring-indigo-400/20"
                        >
                            <Users
                                class="h-5 w-5"
                            />
                        </div>

                        <h3
                            class="text-base font-semibold
                                   text-white"
                        >
                            Smart Team Management
                        </h3>

                        <p
                            class="mt-2 text-sm leading-6
                                   text-slate-400"
                        >
                            Organize departments, teams and
                            executives while keeping ownership
                            clearly defined.
                        </p>

                        <div
                            class="absolute -right-12
                                   -top-12 h-28 w-28
                                   rounded-full
                                   bg-indigo-500/10
                                   blur-2xl
                                   transition group-hover:bg-indigo-500/20"
                        ></div>
                    </div>

                    <!-- Card 3 -->
                    <div
                        class="group relative overflow-hidden
                               rounded-2xl
                               border border-white/10
                               bg-white/[0.035]
                               p-6
                               backdrop-blur-sm
                               transition-all duration-500
                               hover:-translate-y-1
                               hover:border-cyan-400/20
                               hover:bg-white/[0.055]"
                    >
                        <div
                            class="mb-6 flex h-11 w-11
                                   items-center justify-center
                                   rounded-xl
                                   bg-cyan-500/10
                                   text-cyan-400
                                   ring-1 ring-cyan-400/20"
                        >
                            <Bot
                                class="h-5 w-5"
                            />
                        </div>

                        <h3
                            class="text-base font-semibold
                                   text-white"
                        >
                            Intelligent Automation
                        </h3>

                        <p
                            class="mt-2 text-sm leading-6
                                   text-slate-400"
                        >
                            Automate repetitive communication
                            workflows and help teams respond
                            faster.
                        </p>

                        <div
                            class="absolute -right-12
                                   -top-12 h-28 w-28
                                   rounded-full
                                   bg-cyan-500/10
                                   blur-2xl
                                   transition group-hover:bg-cyan-500/20"
                        ></div>
                    </div>

                    <!-- Card 4 -->
                    <div
                        class="group relative overflow-hidden
                               rounded-2xl
                               border border-white/10
                               bg-white/[0.035]
                               p-6
                               backdrop-blur-sm
                               transition-all duration-500
                               hover:-translate-y-1
                               hover:border-violet-400/20
                               hover:bg-white/[0.055]"
                    >
                        <div
                            class="mb-6 flex h-11 w-11
                                   items-center justify-center
                                   rounded-xl
                                   bg-violet-500/10
                                   text-violet-400
                                   ring-1 ring-violet-400/20"
                        >
                            <Network
                                class="h-5 w-5"
                            />
                        </div>

                        <h3
                            class="text-base font-semibold
                                   text-white"
                        >
                            Connected Operations
                        </h3>

                        <p
                            class="mt-2 text-sm leading-6
                                   text-slate-400"
                        >
                            Connect customer ownership,
                            departments and communication
                            workflows into one system.
                        </p>
                    </div>

                    <!-- Card 5 -->
                    <div
                        class="group relative overflow-hidden
                               rounded-2xl
                               border border-white/10
                               bg-white/[0.035]
                               p-6
                               backdrop-blur-sm
                               transition-all duration-500
                               hover:-translate-y-1
                               hover:border-emerald-400/20
                               hover:bg-white/[0.055]"
                    >
                        <div
                            class="mb-6 flex h-11 w-11
                                   items-center justify-center
                                   rounded-xl
                                   bg-emerald-500/10
                                   text-emerald-400
                                   ring-1 ring-emerald-400/20"
                        >
                            <ShieldCheck
                                class="h-5 w-5"
                            />
                        </div>

                        <h3
                            class="text-base font-semibold
                                   text-white"
                        >
                            Controlled Access
                        </h3>

                        <p
                            class="mt-2 text-sm leading-6
                                   text-slate-400"
                        >
                            Role-based access keeps teams,
                            administrators and executives
                            working within the right scope.
                        </p>
                    </div>

                    <!-- Card 6 -->
                    <div
                        class="group relative overflow-hidden
                               rounded-2xl
                               border border-white/10
                               bg-white/[0.035]
                               p-6
                               backdrop-blur-sm
                               transition-all duration-500
                               hover:-translate-y-1
                               hover:border-amber-400/20
                               hover:bg-white/[0.055]"
                    >
                        <div
                            class="mb-6 flex h-11 w-11
                                   items-center justify-center
                                   rounded-xl
                                   bg-amber-500/10
                                   text-amber-400
                                   ring-1 ring-amber-400/20"
                        >
                            <Zap
                                class="h-5 w-5"
                            />
                        </div>

                        <h3
                            class="text-base font-semibold
                                   text-white"
                        >
                            Built for Speed
                        </h3>

                        <p
                            class="mt-2 text-sm leading-6
                                   text-slate-400"
                        >
                            Give customer-facing teams the
                            tools they need to act quickly and
                            keep conversations moving.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- =========================================================
             CTA
        ========================================================== -->

        <section
            class="relative overflow-hidden
                   border-t border-white/5
                   bg-[#070b1c]
                   py-24"
        >
            <div
                class="absolute left-1/2 top-1/2
                       h-96 w-96
                       -translate-x-1/2
                       -translate-y-1/2
                       rounded-full
                       bg-blue-600/15
                       blur-3xl"
            ></div>

            <div
                class="relative mx-auto max-w-3xl
                       px-5 text-center
                       sm:px-8"
            >
                <div
                    class="mx-auto mb-6 flex h-12 w-12
                           items-center justify-center
                           rounded-2xl
                           bg-gradient-to-br
                           from-blue-500
                           to-indigo-600
                           shadow-lg
                           shadow-blue-900/30"
                >
                    <Sparkles
                        class="h-5 w-5 text-white"
                    />
                </div>

                <h2
                    class="text-3xl font-bold
                           tracking-tight
                           text-white
                           sm:text-4xl"
                >
                    Ready to get started?
                </h2>

                <p
                    class="mx-auto mt-4 max-w-xl
                           text-sm leading-6
                           text-slate-400 sm:text-base"
                >
                    Sign in to access your workspace and
                    start managing customer conversations,
                    teams and operations.
                </p>

                <Link
                    href="/login"
                    class="group mt-8 inline-flex
                           items-center gap-2
                           rounded-xl
                           bg-white
                           px-6 py-3.5
                           text-sm font-semibold
                           text-slate-900
                           shadow-xl
                           shadow-black/20
                           transition-all duration-300
                           hover:-translate-y-0.5
                           hover:bg-slate-100"
                >
                    Login to Dashboard

                    <ArrowRight
                        class="h-4 w-4
                               transition-transform
                               duration-300
                               group-hover:translate-x-1"
                    />
                </Link>
            </div>
        </section>

        <!-- =========================================================
             FOOTER
        ========================================================== -->

        <footer
            class="border-t border-white/5
                   bg-[#040611]"
        >
            <div
                class="mx-auto flex max-w-7xl
                       flex-col items-center
                       justify-between gap-5
                       px-5 py-8
                       sm:flex-row
                       sm:px-8 lg:px-10"
            >
                <div
                    class="flex items-center gap-3"
                >
                    <img
                        src="/assets/images/intouch.jpg"
                        alt="InTouch Software Solutions"
                        class="max-h-7 w-auto
                               object-contain invert"
                    />

                    <span
                        class="text-xs text-slate-600"
                    >
                        ×
                    </span>

                    <img
                        src="/assets/images/logo.png"
                        alt="Arihant Capital"
                        class="max-h-7 w-auto
                               object-contain"
                    />
                </div>

                <div
                    class="text-center text-xs
                           text-slate-500
                           sm:text-right"
                >
                    <p>
                        Built by
                        <span
                            class="font-medium
                                   text-slate-400"
                        >
                            InTouch Software Solutions
                        </span>
                    </p>

                    <p class="mt-1">
                        For Arihant Capital
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>