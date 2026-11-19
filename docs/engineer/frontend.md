Audience: Engineer

# Front-end Components Guide

## Who is this for
- Front-end engineers adding or refining shadcn-vue components in the Vue + Inertia app.

## When to use it
- You need a ready-made component (button, card, input) from shadcn-vue.
- During UI refreshes or onboarding when the component library needs additions.

## Steps
1. **Prerequisites**
   - Use Node.js 22 or newer and run a Vite dev server (`npm run dev`).
   - `npm install` should have run so `node_modules/` is populated.
   - Tailwind styles and theme configuration live in `resources/css/app.css`.
2. **Run the shadcn-vue generator**
   ```bash
   npx shadcn-vue@latest add button card input
   ```
   - Choose or confirm the default path (usually `resources/js/components/ui`) so generated components sit next to the existing UI library.
   - When asked about colors or providers, accept the defaults unless the design system requires custom palettes.
3. **Wire components into the app**
   - Import the new components from `resources/js/components/ui` or the folder you chose.
   - Drop the wrappers into the desired Inertia page (`resources/js/pages/...`) or shared component.
   - If you need icons, run the builder again for the icon set or add them manually (remember to import `lucide-vue-next` if using icons).
4. **Lint and format**
   - Run `npm run lint` to catch duplicate props, unused refs, or Tailwind class warnings. The repo uses ESLint’s flat config; add ignore patterns via the `ignores` array in `eslint.config.js` (do not add `.eslintignore`).
   - Run `npm run format` to keep styling consistent (Prettier + Tailwind ordering).
   - Commit only the component files and updated imports.
5. **Document the change**
   - Update the relevant implementation notes when component behavior changes.

## Troubleshooting
- **Tailwind classes not generating.** Check the component path and use complete class names that Tailwind can find in the source.
- **ESLint flags duplicate props.** Look for `vue/no-dupe-keys` or unused refs; remove the duplicate or rename the prop, then rerun `npm run lint`.
- **New components do not show up in the UI.** Confirm the component is imported and registered in the parent and that Vite rebuilt without caching (`npm run dev -- --force` if needed).
- **shadcn-vue fails due to stale node_modules.** Delete `node_modules/`, reinstall (`npm install`), then rerun the generator.
- **Formatting drift.** Run `npm run format` before committing; the command enforces the Tailwind ordering expected by this repo.

## Glossary
- **shadcn-vue** – CLI that scaffolds Tailwind + Vue components; keeps consistent naming and import paths.
- **Tailwind ordering** – Prettier plugin that sorts classes; run `npm run format` so generated `class` attributes stay stable.
- **Wayfinder** – Vue route discovery helper used in other parts of the repo (keep your components aware if you emit links).

## Before you commit
- Run `npm run lint` and `npm run format`.
- Keep ignores in `eslint.config.js` (don’t add `.eslintignore`).
- Commit only generated components and the imports you touched.
