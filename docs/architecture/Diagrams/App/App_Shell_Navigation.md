# App Shell & Navigation

The authenticated application shell and its primary navigation (Sprint 2 / S-3.1.1). Source of truth: `resources/js/components/AppSidebar.vue`, `resources/js/components/NavMain.vue`, `resources/js/pages/Dashboard.vue`, `routes/web.php`, `routes/settings.php`.

## Shell layout & primary nav

```mermaid
flowchart LR
    subgraph Shell["AppSidebarLayout"]
        direction TB
        subgraph Sidebar["AppSidebar"]
            Logo["App logo to /dashboard"]
            subgraph Nav["NavMain (primary)"]
                Workspace["Workspace to /dashboard"]
                Settings["Settings to /settings/*"]
            end
            User["NavUser (account menu)"]
        end
        Content["AppContent (page slot + breadcrumbs)"]
    end

    Workspace -->|"active when path is /dashboard* or /stories/*"| Content
    Settings -->|"active across /settings/* area"| Content
```

## Active-area resolution

```mermaid
flowchart TD
    Url["Current path"] --> Check{"isActive provided on NavItem?"}
    Check -->|"yes"| Use["Use isCurrentOrParentUrl match (prefix)"]
    Check -->|"no"| Fallback["Fallback: exact isCurrentUrl(href)"]
    Use --> Indicate["SidebarMenuButton renders active state"]
    Fallback --> Indicate
```

## Workspace states (Sprint 7)

```mermaid
stateDiagram-v2
    [*] --> Empty
    Empty: "No stories yet" + guidance
    Empty: primary action = New story button → CreateStoryDialog
    Empty --> Populated: author creates first story
    Populated: Card grid (1/2/3 cols responsive)
    Populated: "New story" button in header
    Populated --> Edit: click edit icon on card
    Edit: /stories/slug/edit (dedicated page)
    Edit --> Populated: save or navigate back
    Populated --> Populated: delete (useConfirm → redirect)
```

## Notes

- Only **Workspace** + **Review** + **Settings** are surfaced; **Play** is intentionally deferred to Phase 5 (no dead nav items).
- Settings links to the profile landing (`profile.edit`) but is highlighted across the entire `/settings/*` area via a prefix match, so Profile / Security / Appearance all show the Settings tab active.
- Workspace is active for both `/dashboard` and `/stories/*` paths, keeping the edit page contextually linked.
- The empty state is the required **empty** UI state: it teaches the next step with a create CTA. The populated state shows a responsive card grid with edit (pencil → dedicated page) and delete (trash → useConfirm dialog, never native `confirm()`).
- Story creation is inline via Dialog (desktop); edit is a dedicated page (room for per-story tabs in S-1.2.x).

## Related

- [../../ARCHITECTURE.md](../../ARCHITECTURE.md) §11 — Application foundation (Sprint 2)
- [Auth_Signin_Flow.md](./Auth_Signin_Flow.md) — sign-in & route protection
- [../../../manual-qa-check/ui/S-2-account-shell.md](../../../manual-qa-check/ui/S-2-account-shell.md) — manual QA path
