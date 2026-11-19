import { computed, ref, type ComputedRef, type Ref } from 'vue'
import { createContext } from 'reka-ui'

export const SIDEBAR_COOKIE_NAME = 'sidebar_state'
export const SIDEBAR_COOKIE_MAX_AGE = 60 * 60 * 24 * 7
export const SIDEBAR_WIDTH = '16rem'
export const SIDEBAR_WIDTH_MOBILE = '18rem'
export const SIDEBAR_WIDTH_ICON = '3rem'
export const SIDEBAR_KEYBOARD_SHORTCUT = 'b'

type SidebarContext = {
  state: ComputedRef<'expanded' | 'collapsed'>
  open: Ref<boolean>
  setOpen: (value: boolean) => void
  isMobile: Ref<boolean>
  openMobile: Ref<boolean>
  setOpenMobile: (value: boolean) => void
  toggleSidebar: () => void
}

const [useSidebarContext, provideSidebarContext] = createContext<SidebarContext>('Sidebar')

const fallbackOpen = ref(true)
const fallbackOpenMobile = ref(false)

const fallbackSidebarContext: SidebarContext = {
  state: computed(() => (fallbackOpen.value ? 'expanded' : 'collapsed')),
  open: fallbackOpen,
  setOpen: (value: boolean) => {
    fallbackOpen.value = value
  },
  isMobile: ref(false),
  openMobile: fallbackOpenMobile,
  setOpenMobile: (value: boolean) => {
    fallbackOpenMobile.value = value
  },
  toggleSidebar: () => {
    fallbackOpen.value = !fallbackOpen.value
  },
}

export const useSidebar = () => useSidebarContext(fallbackSidebarContext)

export { provideSidebarContext }
