<script setup>
import { watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { useToast } from "@/Composables/useToast";

const page = usePage();
const { success, error, info } = useToast();

const shown = new Set();

const showFlash = (value, type, fallback) => {
    if (!value) {
        return;
    }

    const message = String(value);
    const key = `${type}:${message}`;

    if (shown.has(key)) {
        return;
    }

    shown.add(key);
    window.setTimeout(() => shown.delete(key), 5000);
    ({ success, error, info }[type] ?? info)(message || fallback);
};

watch(
    () => page.props.flash,
    (flash) => {
        showFlash(flash?.success, "success", "Operation completed.");
        showFlash(flash?.error, "error", "Something went wrong.");
        showFlash(flash?.info, "info", "Information");
        showFlash(flash?.status, "info", "Request completed.");
    },
    { immediate: true, deep: true },
);

router.on("error", (event) => {
    const errors = event.detail?.errors ?? {};
    const firstError = Object.values(errors).flat()?.[0];

    if (firstError) {
        showFlash(firstError, "error", "Please check the form.");
    }
});
</script>
