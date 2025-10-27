<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()

const menuItems = [
  {
    title: 'Dashboard',
    icon: 'dashboard',
    to: '/admin',
  },
  {
    title: 'Template Families',
    icon: 'folder',
    to: '/admin/families',
  },
  {
    title: 'Templates',
    icon: 'description',
    to: '/admin/templates',
  },
  {
    title: 'Template Data',
    icon: 'data_object',
    to: '/admin/data',
  },
  {
    title: 'Rendering Jobs',
    icon: 'work',
    to: '/admin/jobs',
  },
  {
    title: 'Assets',
    icon: 'collections',
    to: '/admin/assets',
  },
  {
    title: 'Settings',
    icon: 'settings',
    to: '/admin/settings',
  },
]

const isSidebarMinimized = ref(false)
const isMobileMenuOpen = ref(false)

const currentPath = computed(() => page.url)

function isActive(path: string): boolean {
  if (path === '/admin') {
    return currentPath.value === '/admin'
  }
  return currentPath.value.startsWith(path)
}

function toggleSidebar() {
  isSidebarMinimized.value = !isSidebarMinimized.value
}

function closeMobileMenu() {
  isMobileMenuOpen.value = false
}
</script>

<template>
  <div class="admin-layout">
    <!-- Sidebar -->
    <aside
      class="sidebar"
      :class="{
        minimized: isSidebarMinimized,
        'mobile-open': isMobileMenuOpen,
      }"
    >
      <!-- Logo -->
      <div class="sidebar-header">
        <Link href="/" class="logo">
          <span v-if="!isSidebarMinimized">Truth Admin</span>
          <span v-else>T</span>
        </Link>
      </div>

      <!-- Navigation -->
      <nav class="sidebar-nav">
        <Link
          v-for="item in menuItems"
          :key="item.to"
          :href="item.to"
          class="nav-item"
          :class="{ active: isActive(item.to) }"
          @click="closeMobileMenu"
        >
          <va-icon :name="item.icon" class="nav-icon" />
          <span v-if="!isSidebarMinimized" class="nav-title">{{ item.title }}</span>
        </Link>
      </nav>

      <!-- Toggle Button -->
      <button
        class="sidebar-toggle"
        @click="toggleSidebar"
        :title="isSidebarMinimized ? 'Expand Sidebar' : 'Collapse Sidebar'"
      >
        <va-icon :name="isSidebarMinimized ? 'chevron_right' : 'chevron_left'" />
      </button>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
      <!-- Top Navbar -->
      <header class="top-navbar">
        <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" @click="isMobileMenuOpen = !isMobileMenuOpen">
          <va-icon name="menu" />
        </button>

        <!-- Breadcrumbs -->
        <div class="breadcrumbs">
          <Link href="/admin" class="breadcrumb-item">Admin</Link>
          <!-- Dynamic breadcrumbs can be added here -->
        </div>

        <div class="spacer" />

        <!-- User Menu -->
        <div class="user-menu">
          <va-button preset="plain" icon="notifications" />
          <va-button preset="plain" icon="account_circle" />
        </div>
      </header>

      <!-- Page Content -->
      <main class="page-content">
        <slot />
      </main>
    </div>

    <!-- Mobile Overlay -->
    <div
      v-if="isMobileMenuOpen"
      class="mobile-overlay"
      @click="closeMobileMenu"
    />
  </div>
</template>

<style scoped>
.admin-layout {
  display: flex;
  min-height: 100vh;
  background: var(--va-background-primary);
}

/* Sidebar */
.sidebar {
  width: 260px;
  background: var(--va-background-element);
  border-right: 1px solid var(--va-background-border);
  display: flex;
  flex-direction: column;
  transition: width 0.3s ease;
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  z-index: 1000;
}

.sidebar.minimized {
  width: 72px;
}

.sidebar-header {
  padding: 1.5rem 1rem;
  border-bottom: 1px solid var(--va-background-border);
}

.logo {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--va-primary);
  text-decoration: none;
  display: block;
  text-align: center;
}

.sidebar-nav {
  flex: 1;
  padding: 1rem 0;
  overflow-y: auto;
}

.nav-item {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  color: var(--va-text-primary);
  text-decoration: none;
  transition: all 0.2s;
  gap: 0.75rem;
  margin: 0.25rem 0.5rem;
  border-radius: 0.5rem;
}

.nav-item:hover {
  background: var(--va-background-hover);
}

.nav-item.active {
  background: var(--va-primary);
  color: white;
}

.nav-icon {
  font-size: 1.25rem;
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.nav-icon :deep(.material-icons) {
  font-size: 1.25rem;
}

.nav-title {
  font-size: 0.875rem;
  font-weight: 500;
  white-space: nowrap;
}

.sidebar.minimized .nav-item {
  justify-content: center;
}

.sidebar-toggle {
  padding: 1rem;
  border: none;
  background: transparent;
  color: var(--va-text-primary);
  cursor: pointer;
  border-top: 1px solid var(--va-background-border);
  transition: background 0.2s;
}

.sidebar-toggle:hover {
  background: var(--va-background-hover);
}

/* Main Content */
.main-content {
  flex: 1;
  margin-left: 260px;
  transition: margin-left 0.3s ease;
}

.sidebar.minimized ~ .main-content {
  margin-left: 72px;
}

/* Top Navbar */
.top-navbar {
  height: 64px;
  background: var(--va-background-element);
  border-bottom: 1px solid var(--va-background-border);
  display: flex;
  align-items: center;
  padding: 0 1.5rem;
  gap: 1rem;
  position: sticky;
  top: 0;
  z-index: 900;
}

.mobile-menu-btn {
  display: none;
  background: none;
  border: none;
  color: var(--va-text-primary);
  cursor: pointer;
  padding: 0.5rem;
}

.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.breadcrumb-item {
  color: var(--va-text-primary);
  text-decoration: none;
  transition: color 0.2s;
}

.breadcrumb-item:hover {
  color: var(--va-primary);
}

.spacer {
  flex: 1;
}

.user-menu {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* Page Content */
.page-content {
  padding: 2rem;
  min-height: calc(100vh - 64px);
}

/* Mobile Responsive */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    transition: transform 0.3s ease;
  }

  .sidebar.mobile-open {
    transform: translateX(0);
  }

  .main-content {
    margin-left: 0;
  }

  .mobile-menu-btn {
    display: block;
  }

  .mobile-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
  }
}
</style>
