import { useUserStore } from '@/store/user';

export default {
  mounted(el, binding) {
    const userStore = useUserStore();
    const value = binding.value;
    const codes = Array.isArray(value) ? value : [value];
    const allowed = codes.some((code) => userStore.hasPerm(code));

    if (!allowed) el.remove();
  },
};
