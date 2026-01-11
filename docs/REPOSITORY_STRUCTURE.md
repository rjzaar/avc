# AVC Repository Structure for NWP

## Current State

| Repository | Location | Purpose |
|------------|----------|---------|
| `nwp` | github.com/rjzaar/nwp | NWP tooling, scripts, site management |
| `avcgs` | github.com/rjzaar/avcgs | AVC specs, planning, documentation |
| `workflow_assignment` | github.com/rjzaar/workflow_assignment | General-purpose workflow module |
| `avc` site | ~/nwp/avc/ | Open Social instance (not a separate repo) |

---

## Recommended Repository Structure

### Option A: AVC Distribution Profile (Recommended)

Create an **avc_profile** Drupal install profile that bundles all AVC-specific code.

```
Repositories:
├── avcgs (existing)
│   └── Specs, planning, documentation
│
├── workflow_assignment (existing)
│   └── General-purpose workflow module (can be used outside AVC)
│
└── avc_profile (NEW)
    └── Drupal install profile containing all AVC-specific modules
```

#### avc_profile Repository Structure

```
avc_profile/
├── avc_profile.info.yml          # Profile definition
├── avc_profile.install           # Installation hooks
├── avc_profile.profile           # Profile hooks
├── composer.json                 # Dependencies (workflow_assignment, etc.)
├── config/
│   ├── install/                  # Config installed with profile
│   │   ├── taxonomy.vocabulary.member_skills.yml
│   │   ├── group.type.guild.yml
│   │   └── ...
│   └── optional/                 # Optional config
│
├── modules/
│   ├── avc_core/                 # Shared services, base functionality
│   │   ├── avc_core.info.yml
│   │   ├── avc_core.module
│   │   └── src/
│   │
│   ├── avc_member/               # Member profiles, dashboards
│   │   ├── avc_member.info.yml
│   │   ├── avc_member.module
│   │   └── src/
│   │
│   ├── avc_group/                # Group workflow integration
│   │   ├── avc_group.info.yml
│   │   └── src/
│   │
│   ├── avc_guild/                # Guild group type, scoring, endorsements
│   │   ├── avc_guild.info.yml
│   │   └── src/
│   │
│   ├── avc_asset/                # Projects, Documents, Resources
│   │   ├── avc_asset.info.yml
│   │   └── src/
│   │
│   └── avc_notification/         # Advanced notification system
│       ├── avc_notification.info.yml
│       └── src/
│
├── themes/
│   └── avc_theme/                # Custom theme (optional, extend socialbase)
│       ├── avc_theme.info.yml
│       └── ...
│
└── README.md
```

#### avc_profile.info.yml

```yaml
name: 'AV Commons'
type: profile
description: 'AV Commons collaborative workflow platform built on Open Social'
core_version_requirement: ^10
base profile: social
install:
  - avc_core
  - avc_member
  - avc_group
  - avc_guild
  - avc_asset
  - avc_notification
dependencies:
  - workflow_assignment:workflow_assignment
```

#### Composer Integration

```json
{
  "name": "rjzaar/avc_profile",
  "type": "drupal-profile",
  "require": {
    "rjzaar/workflow_assignment": "^1.0",
    "goalgorilla/open_social": "^12.4"
  }
}
```

---

### Option B: Monorepo with Separate Modules (Simpler)

Keep all AVC modules in a single repo but as standalone modules (not a profile).

```
avc_modules/
├── composer.json                 # Defines path repositories
├── avc_core/
│   └── composer.json
├── avc_member/
│   └── composer.json
├── avc_group/
│   └── composer.json
├── avc_guild/
│   └── composer.json
├── avc_asset/
│   └── composer.json
└── avc_notification/
    └── composer.json
```

**Site composer.json:**
```json
{
  "repositories": {
    "avc_modules": {
      "type": "path",
      "url": "../avc_modules/*"
    }
  },
  "require": {
    "rjzaar/avc_core": "*",
    "rjzaar/avc_member": "*"
  }
}
```

---

### Option C: Individual Repos per Module (Most Flexible)

Each module has its own repository.

```
Repositories:
├── workflow_assignment (existing)
├── avc_core
├── avc_member
├── avc_group
├── avc_guild
├── avc_asset
└── avc_notification
```

**Pros:** Maximum flexibility, independent versioning
**Cons:** More repos to manage, harder to coordinate changes

---

## Recommendation: Option A (AVC Profile)

### Why Profile Approach?

1. **Bundled deployment**: All AVC code deploys together
2. **Config management**: Profile config syncs correctly
3. **Open Social compatible**: Profiles can extend Open Social
4. **Single composer require**: Sites just require the profile
5. **Easier testing**: Test entire AVC stack together
6. **NWP integration**: Works well with cnwp.yml recipes

### Repository Hosting

| Repository | Host | Visibility |
|------------|------|------------|
| avcgs | github.com | Public |
| workflow_assignment | github.com | Public |
| avc_profile | git.nwpcode.org OR github.com | Private/Public |

---

## NWP Integration

### cnwp.yml Recipe for AVC

```yaml
recipes:
  avc:
    type: opensocial
    version: "12.4"
    profile: avc_profile
    composer_require:
      - rjzaar/avc_profile:dev-main
      - rjzaar/workflow_assignment:dev-main
    post_install:
      - drush en avc_core avc_member avc_group avc_guild avc_asset avc_notification -y
```

### Site Creation

```bash
# Using NWP pl command
pl install avc mysite

# This would:
# 1. Create Open Social site
# 2. Require avc_profile
# 3. Install all AVC modules
# 4. Apply AVC configuration
```

---

## Git Workflow

### Branch Strategy

```
main          # Stable releases
├── develop   # Integration branch
├── feature/* # New features
├── fix/*     # Bug fixes
└── release/* # Release preparation
```

### Versioning

Use semantic versioning aligned with Open Social:

| Open Social | AVC Profile | Notes |
|-------------|-------------|-------|
| 12.4.x | 1.0.x | Initial release |
| 12.5.x | 1.1.x | Minor updates |
| 13.x | 2.0.x | Major update |

---

## Development Workflow

### Local Development

```bash
# 1. Clone avc_profile into custom modules
cd ~/nwp/avc/html/profiles/
git clone git@github.com:rjzaar/avc_profile.git

# 2. Or use composer path for development
# In composer.json:
{
  "repositories": {
    "avc_profile": {
      "type": "path",
      "url": "/home/rob/dev/avc_profile"
    }
  }
}
```

### Testing

```bash
# Run PHPUnit tests
cd ~/nwp/avc/html/profiles/avc_profile
../../../vendor/bin/phpunit

# Run with NWP test framework
pl test avc
```

---

## File Structure Summary

```
~/nwp/
├── cnwp.yml                      # Site configuration
├── avc/                          # AVC Open Social site
│   ├── html/
│   │   ├── profiles/
│   │   │   └── avc_profile/     # AVC profile (git submodule or clone)
│   │   └── modules/
│   │       └── custom/
│   │           └── workflow_assignment/  # General workflow module
│   └── composer.json
│
└── git repos (local development)/
    ├── avcgs/                    # Specs, documentation
    ├── workflow_assignment/      # Workflow module
    └── avc_profile/              # AVC profile with all modules

GitHub/GitLab:
├── rjzaar/avcgs                  # Specs
├── rjzaar/workflow_assignment    # Workflow module
└── rjzaar/avc_profile            # AVC profile (all AVC modules)
```

---

## Next Steps

1. **Create avc_profile repository**
   ```bash
   mkdir -p ~/dev/avc_profile
   cd ~/dev/avc_profile
   git init
   ```

2. **Set up profile structure**
   - Create avc_profile.info.yml
   - Create modules/ directory
   - Move/create avc_* modules

3. **Configure NWP recipe**
   - Add avc recipe to cnwp.yml
   - Test installation

4. **Push to GitLab/GitHub**
   ```bash
   git remote add origin git@git.nwpcode.org:root/avc_profile.git
   git push -u origin main
   ```

---

*Document created: 2026-01-02*
*Aligned with: IMPLEMENTATION_PLAN.md*
