# AVC Mobile App Development Options

An investigation into building Android/iOS apps with AVC functionality, ranked from easiest to most complex.

## AVC Features to Replicate

| Feature | Description |
|---------|-------------|
| **User Profiles** | Member dashboards, worklists |
| **Groups** | Group workflows, task dashboards |
| **Guilds** | Mentorship, scoring, endorsements |
| **Workflows** | Task assignment to users/groups |
| **Assets** | Project/document management |
| **Notifications** | Push notifications, digests |
| **Activity Feeds** | Social activity streams |

---

## Complexity Assessment: Can Each Option Handle AVC?

AVC is **not a simple app**. It's essentially:
- **Social network** (Open Social foundation)
- **Workflow engine** (state machines, task routing)
- **Group/Guild management** (permissions, mentorship, scoring)
- **Asset management** (documents, projects)
- **Custom dashboards** (worklists, activity feeds)

### Feature-by-Feature Analysis

#### 1. FlutterFlow + Firebase

| Feature | Can Handle? | Notes |
|---------|-------------|-------|
| User profiles | Yes | Basic auth + Firestore |
| Groups | Partial | Manual permission logic |
| Guilds (mentorship, scoring) | Difficult | Complex data relationships |
| Workflow state machine | No | No built-in workflow engine |
| Task assignment | Basic only | No complex routing |
| Asset management | Yes | Firebase Storage |
| Notifications | Yes | FCM built-in |
| Activity feeds | Manual | Must build from scratch |

**Verdict: NO** - Too complex for no-code. You'd outgrow it quickly.

#### 2. Flet + Supabase

| Feature | Can Handle? | Notes |
|---------|-------------|-------|
| User profiles | Yes | Supabase Auth + tables |
| Groups | Yes | PostgreSQL + RLS |
| Guilds (mentorship, scoring) | Possible | Custom tables + logic |
| Workflow state machine | Manual | Need to build yourself |
| Task assignment | Possible | Edge Functions + triggers |
| Asset management | Yes | Supabase Storage |
| Notifications | Partial | No push notifications built-in |
| Activity feeds | Manual | Real-time subscriptions help |

**Verdict: MAYBE** - Can technically handle it, but:
- Flet is immature (pre-1.0)
- You'd build most workflow logic from scratch
- No existing packages for guild/mentorship patterns
- Significant custom development needed

#### 3. Flutter + FastAPI

| Feature | Can Handle? | Notes |
|---------|-------------|-------|
| User profiles | Yes | JWT + SQLAlchemy models |
| Groups | Yes | Custom models + permissions |
| Guilds (mentorship, scoring) | Yes | Full control to model |
| Workflow state machine | Yes | Use `transitions` library or custom |
| Task assignment | Yes | Full backend control |
| Asset management | Yes | S3/MinIO integration |
| Notifications | Yes | FCM + Celery |
| Activity feeds | Yes | Custom implementation |

**Verdict: YES** - Can handle full complexity, but:
- Requires learning Dart for Flutter frontend
- Significant development effort
- You're building AVC from scratch

#### 4. React Native + Django REST

| Feature | Can Handle? | Notes |
|---------|-------------|-------|
| User profiles | Yes | Django User + DRF |
| Groups | Yes | **django-organizations** |
| Guilds (mentorship, scoring) | Yes | Custom models |
| Workflow state machine | Yes | **django-river** (designed for this) |
| Task assignment | Yes | **django-todo** or custom |
| Asset management | Yes | Django + S3 |
| Notifications | Yes | **django-notifications-hq** |
| Activity feeds | Yes | **django-activity-stream** |

**Verdict: YES - BEST OPTION** - Best existing package ecosystem:
- `django-river` = workflow state machine (like Drupal's workflow_assignment)
- `django-notifications-hq` = notification system
- `django-activity-stream` = activity feeds
- Django admin = content management

#### 5. BeeWare + FastAPI

| Feature | Can Handle? | Notes |
|---------|-------------|-------|
| All backend features | Yes | Same as FastAPI option |
| Mobile UI complexity | Limited | Toga widget library is limited |
| Complex dashboards | Difficult | Less mature UI toolkit |
| Real-time updates | Harder | Less WebSocket support |

**Verdict: NO** - BeeWare's frontend isn't mature enough for AVC's UI complexity.

#### 6. No-Code (Adalo/Bubble/Glide)

**Verdict: NO** - AVC's workflow logic, guild system, and dashboard complexity exceeds what no-code platforms handle well.

### Summary: Can It Handle AVC?

| Option | Handle AVC? | Effort | Python % | Status |
|--------|-------------|--------|----------|--------|
| FlutterFlow + Firebase | No | Low | 0% | Too simple |
| Flet + Supabase | Maybe | High | 100% | Immature |
| **Flutter + FastAPI** | Yes | High | 50% | Viable |
| **React Native + Django** | Yes | High | 50% | **Best packages** |
| BeeWare + FastAPI | No | High | 100% | UI too limited |
| No-Code | No | Low | 0% | Too simple |

### Development Effort Reality

AVC on Drupal/Open Social benefits from:
- **Years of development** in Open Social
- **Drupal's mature ecosystem** (workflow, groups, notifications)
- **Pre-built social features** you'd need to recreate

Building AVC-equivalent in mobile means **rebuilding most of this from scratch**, regardless of framework.

| Approach | Estimated Development Effort |
|----------|------------------------------|
| Simplified AVC (core features only) | 3-6 months |
| Full AVC feature parity | 9-12+ months |

---

## Option Rankings (Easiest to Hardest)

### EASIEST: FlutterFlow + Firebase

**Development Time:** Fastest
**Coding Required:** Minimal
**Python:** No (but easiest overall)

FlutterFlow is a low-code builder for Flutter apps with a visual interface.

```
FlutterFlow (Visual Builder)
├── Drag-and-drop UI design
├── Firebase backend (built-in)
├── User authentication
├── Real-time database
├── Push notifications
└── Exports to Flutter code
```

**Pros:**
- Visual drag-and-drop interface
- Firebase integration out of the box
- Generates clean Flutter/Dart code
- Can export and customize later
- Community features via Firebase

**Cons:**
- Subscription cost ($30-70/month)
- Less control than custom code
- Not Python-based

**Best For:** Rapid prototyping, MVP, non-developers

**Links:**
- [FlutterFlow](https://flutterflow.io/)
- [Firebase](https://firebase.google.com/)

---

### EASY + PYTHON: Flet + Supabase

**Development Time:** Fast
**Coding Required:** Python only
**Python:** Yes

Flet is a Python framework that builds Flutter apps without writing Dart.

```
Flet (Python)
├── Flutter widgets via Python
├── Single codebase for iOS/Android/Web/Desktop
├── Hot reload development
└── Native API access (Pyjnius/Pyobjus)

Supabase (Backend)
├── PostgreSQL database
├── Authentication
├── Real-time subscriptions
├── Row-level security
└── REST API auto-generated
```

**Sample Project Structure:**
```python
# requirements.txt
flet>=0.25
supabase-py
```

```python
# main.py
import flet as ft
from supabase import create_client

def main(page: ft.Page):
    page.title = "AVC Mobile"

    # Supabase connection
    supabase = create_client(SUPABASE_URL, SUPABASE_KEY)

    # Task list view
    tasks = ft.ListView(expand=True)

    async def load_tasks():
        response = supabase.table("tasks").select("*").execute()
        for task in response.data:
            tasks.controls.append(
                ft.ListTile(title=ft.Text(task["title"]))
            )
        page.update()

    page.add(tasks)
    page.run_task(load_tasks)

ft.app(target=main)
```

**Pros:**
- 100% Python development
- Modern Flutter UI
- Supabase is open-source (can self-host)
- SQL database (familiar, powerful)
- Real-time features built-in
- Flet v1 releasing late 2025

**Cons:**
- Flet still maturing (pre-1.0)
- Some Flutter widgets not yet wrapped
- Smaller community than Flutter/React Native

**Best For:** Python developers wanting mobile apps

**Links:**
- [Flet](https://flet.dev/)
- [Supabase](https://supabase.com/)
- [Flet + Supabase Example](https://github.com/nichochar/flet-chat)

---

### MODERATE: Flutter + Django/FastAPI Backend

**Development Time:** Moderate
**Coding Required:** Dart + Python
**Python Backend:** Yes

Flutter for the mobile app, Python for the backend API.

```
Flutter App (Dart)
├── Beautiful native UI
├── Cross-platform (iOS/Android)
├── State management (Riverpod/Bloc)
└── HTTP client for API calls

FastAPI Backend (Python)
├── High-performance async API
├── Auto-generated docs (OpenAPI)
├── JWT authentication
├── PostgreSQL + SQLAlchemy
├── WebSockets for real-time
└── Celery for background tasks
```

**Backend Architecture:**
```python
# FastAPI backend structure
backend/
├── app/
│   ├── main.py           # FastAPI app
│   ├── models/
│   │   ├── user.py       # User/Member models
│   │   ├── group.py      # Group/Guild models
│   │   ├── task.py       # Workflow/Task models
│   │   └── asset.py      # Asset/Document models
│   ├── routers/
│   │   ├── auth.py       # Authentication endpoints
│   │   ├── users.py      # User management
│   │   ├── groups.py     # Group/Guild endpoints
│   │   ├── tasks.py      # Workflow/Task endpoints
│   │   └── notifications.py
│   ├── services/
│   │   ├── workflow.py   # Workflow state machine
│   │   └── notifications.py
│   └── core/
│       ├── config.py
│       └── security.py
├── requirements.txt
└── docker-compose.yml
```

```python
# requirements.txt (backend)
fastapi>=0.115
uvicorn[standard]
sqlalchemy>=2.0
asyncpg
python-jose[cryptography]  # JWT
passlib[bcrypt]            # Password hashing
celery[redis]              # Background tasks
firebase-admin             # Push notifications
```

**Pros:**
- Flutter has largest cross-platform adoption
- FastAPI is high-performance
- Full control over backend
- Can reuse backend for web app
- Large ecosystem and community

**Cons:**
- Need to learn Dart for Flutter
- Two codebases to maintain
- More setup and infrastructure

**Best For:** Production apps, teams with capacity

**Open Source Flutter Starters:**
- [Taskist](https://github.com/huextrat/Taskist) - Task management with Firebase
- [Flutter Group Chat](https://github.com/RodrigoBertotti/flutter_group_chat_app_with_firebase) - Groups, chat, video calls
- [Tasky](https://github.com/RegNex/Tasky-Mobile-App) - Task manager with serverless backend

**Links:**
- [Flutter](https://flutter.dev/)
- [FastAPI](https://fastapi.tiangolo.com/)

---

### MODERATE-HARD: React Native + Django REST

**Development Time:** Moderate
**Coding Required:** JavaScript/TypeScript + Python
**Python Backend:** Yes

React Native for mobile, Django REST Framework for backend.

```
React Native App (JavaScript/TypeScript)
├── Native components
├── Large ecosystem (npm)
├── Expo for easy development
└── Redux/Zustand for state

Django REST Backend (Python)
├── Battle-tested framework
├── Built-in admin panel
├── Django ORM
├── django-river for workflows
├── django-notifications
└── Channels for WebSockets
```

**Pros:**
- JavaScript developers readily available
- Expo simplifies mobile development
- Django is mature and full-featured
- Admin panel for content management
- Huge community and packages

**Cons:**
- JavaScript required for frontend
- React Native bridge can be limiting
- Django is heavier than FastAPI

**Best For:** Teams with JavaScript experience

**Links:**
- [React Native](https://reactnative.dev/)
- [Expo](https://expo.dev/)
- [Django REST Framework](https://www.django-rest-framework.org/)

---

### PYTHON-ONLY: BeeWare + FastAPI

**Development Time:** Longer
**Coding Required:** Python only
**Python:** Yes

BeeWare creates native apps using native UI components.

```
BeeWare (Python)
├── Toga - Native UI toolkit
├── Briefcase - App packaging
├── Native look and feel per platform
└── Python all the way down

FastAPI Backend (Python)
└── Same as Option 3
```

**Pros:**
- 100% Python
- True native UI (not custom widgets)
- Open source and growing

**Cons:**
- Less mature than other options
- Smaller community
- Some platform features missing
- More bugs to work around

**Best For:** Python purists, simpler apps

**Links:**
- [BeeWare](https://beeware.org/)
- [Toga UI Toolkit](https://toga.readthedocs.io/)

---

### NO-CODE: Adalo / Bubble / Glide

**Development Time:** Fastest
**Coding Required:** None
**Python:** No

For non-technical implementation.

| Platform | Best For | Mobile |
|----------|----------|--------|
| **Adalo** | Native mobile apps | iOS/Android native |
| **Bubble** | Complex logic, web+mobile | Wrapped native |
| **Glide** | Data-driven apps | PWA |
| **Softr** | Airtable-based apps | PWA |

**Pros:**
- No coding required
- Visual builders
- Fast iteration

**Cons:**
- Subscription costs
- Limited customization
- Vendor lock-in
- May not handle complex workflows

**Links:**
- [Adalo](https://www.adalo.com/)
- [Bubble](https://bubble.io/)
- [Glide](https://www.glideapps.com/)

---

## Recommendation Summary (Updated with Complexity Assessment)

| Priority | Option | Can Handle AVC? | Why |
|----------|--------|-----------------|-----|
| **Best for AVC** | React Native + Django | Yes | Best existing packages for workflows, notifications, activity |
| **Also Viable** | Flutter + FastAPI | Yes | High performance, but more custom code |
| **Simplified AVC Only** | Flet + Supabase | Maybe | 100% Python but immature, significant custom work |
| **Not Recommended** | FlutterFlow, BeeWare, No-Code | No | Cannot handle AVC's complexity |

---

## Primary Recommendation: React Native + Django REST

For AVC's full complexity, **React Native + Django REST** is the best choice:

### Why This Stack?

1. **django-river** - Workflow state machine that mirrors Drupal's workflow_assignment
2. **django-organizations** - Group management with permissions
3. **django-notifications-hq** - Full notification system with digests
4. **django-activity-stream** - Activity feeds like Open Social
5. **Django Admin** - Content management (like Drupal admin)
6. **React Native** - Battle-tested mobile framework
7. **Expo** - Simplifies mobile development significantly

### Django Packages That Map to AVC Modules

| AVC Module | Django Package | Purpose |
|------------|----------------|---------|
| workflow_assignment | **django-river** | On-the-fly workflow state machine |
| avc_group | **django-organizations** | Multi-tenant organizations/groups |
| avc_notification | **django-notifications-hq** | User notifications with preferences |
| avc_member (activity) | **django-activity-stream** | Activity feeds and streams |
| avc_asset | **django-storages** + S3 | File/document management |
| Open Social comments | **django-comments-xtd** | Threaded comments |

### Backend Architecture

```python
# requirements.txt
django>=5.0
djangorestframework>=3.15
django-river>=3.2              # Workflow state machine
django-organizations>=2.0      # Groups/organizations
django-notifications-hq>=1.8   # Notifications
django-activity-stream>=2.0    # Activity feeds
django-storages[s3]            # Asset storage
django-cors-headers            # Mobile app CORS
djangorestframework-simplejwt  # JWT auth for mobile
channels[daphne]               # WebSockets for real-time
celery[redis]                  # Background tasks
```

```
backend/
├── config/
│   ├── settings.py
│   └── urls.py
├── apps/
│   ├── members/           # User profiles, dashboards
│   ├── groups/            # Groups using django-organizations
│   ├── guilds/            # Extended group type with mentorship
│   ├── workflows/         # django-river integration
│   ├── assets/            # Document/project management
│   └── notifications/     # django-notifications-hq config
├── api/
│   └── v1/
│       ├── serializers/
│       └── views/
└── manage.py
```

### React Native Frontend

```
mobile/
├── src/
│   ├── screens/
│   │   ├── Dashboard/
│   │   ├── Groups/
│   │   ├── Guilds/
│   │   ├── Tasks/
│   │   ├── Assets/
│   │   └── Profile/
│   ├── components/
│   ├── services/
│   │   └── api.ts         # Django REST API client
│   └── store/             # Redux/Zustand state
├── app.json
└── package.json
```

### Getting Started

```bash
# Backend
mkdir avc-backend && cd avc-backend
python -m venv venv && source venv/bin/activate
pip install django djangorestframework django-river
django-admin startproject config .
python manage.py startapp members

# Frontend (with Expo)
npx create-expo-app avc-mobile
cd avc-mobile
npx expo start
```

---

## Alternative: Flutter + FastAPI (If You Prefer Flutter)

If you prefer Flutter's UI or need higher API performance:

### Why This Stack?

1. **Flutter** - More popular than React Native, beautiful UI
2. **FastAPI** - Faster than Django for pure API workloads
3. **Full control** - Build exactly what you need
4. **Python backend** - 50% of code is Python

### Trade-offs vs Django

| Aspect | Django REST | FastAPI |
|--------|-------------|---------|
| Workflow packages | django-river | Build yourself |
| Notifications | django-notifications-hq | Build yourself |
| Activity streams | django-activity-stream | Build yourself |
| Admin panel | Built-in | Build yourself |
| API Performance | Good | Better |
| Async support | Partial | Native |

**Bottom line:** FastAPI is faster but you'll write more code.

---

## Simplified AVC Option: Flet + Supabase (Python Only)

If you want 100% Python and can accept a **simplified feature set**:

### What You'd Need to Cut

| Full AVC Feature | Simplified Version |
|------------------|-------------------|
| Complex workflow states | Simple status field |
| Guild mentorship/scoring | Basic group membership |
| Digest notifications | Simple push only |
| Activity streams | Recent items list |
| Advanced dashboards | Basic task lists |

### Feature Implementation Map

| AVC Feature | Flet Component | Supabase Feature |
|-------------|----------------|------------------|
| User Profiles | Custom views | Auth + profiles table |
| Groups/Guilds | ListView, Cards | Tables with RLS |
| Workflows | State management | Edge Functions |
| Task Assignment | Forms, Lists | Foreign keys + triggers |
| Notifications | Snackbars | Realtime + webhooks |
| Assets | FileUploader | Storage buckets |
| Activity Feed | ListView | Real-time subscriptions |

### Getting Started

```bash
# Install Flet
pip install flet

# Create new project
mkdir avc-mobile && cd avc-mobile

# Create main.py and run
flet create myapp
cd myapp
flet run

# For mobile testing
flet run --android  # or --ios
```

### Honest Assessment

Flet + Supabase **can work** for a simplified AVC, but:
- Flet is pre-1.0 (expect bugs, API changes)
- No workflow state machine packages
- No notification digest system
- You're building 80% of logic from scratch
- Better suited for simpler apps

---

## Hybrid Approach: Keep Drupal Backend + Mobile Frontend

An alternative worth considering: **keep the existing Drupal/AVC backend** and add a mobile frontend.

### Why Consider This?

| Benefit | Details |
|---------|---------|
| **No backend rebuild** | AVC's workflow, groups, notifications already work |
| **Drupal JSON:API** | Built-in REST API for headless usage |
| **Faster to market** | Only build the mobile UI |
| **Shared data** | Web and mobile use same backend |

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     MOBILE APPS                             │
│  ┌─────────────────┐  ┌─────────────────┐                  │
│  │  React Native   │  │     Flutter     │                  │
│  │   or Flet App   │  │      App        │                  │
│  └────────┬────────┘  └────────┬────────┘                  │
│           └──────────┬─────────┘                            │
└──────────────────────┼──────────────────────────────────────┘
                       │ JSON:API / REST
┌──────────────────────┼──────────────────────────────────────┐
│                      ▼                                      │
│  ┌───────────────────────────────────────┐                 │
│  │     EXISTING DRUPAL/AVC BACKEND       │                 │
│  │  • JSON:API module (built-in)         │                 │
│  │  • All AVC modules working            │                 │
│  │  • Open Social features               │                 │
│  │  • Workflow assignment                │                 │
│  │  • Groups, Guilds, Assets             │                 │
│  └───────────────────────────────────────┘                 │
│                                                             │
│  ┌───────────────────────────────────────┐                 │
│  │         Existing Database             │                 │
│  └───────────────────────────────────────┘                 │
└─────────────────────────────────────────────────────────────┘
```

### Implementation Steps

1. Enable Drupal JSON:API module (already in core)
2. Configure CORS for mobile app access
3. Set up JWT authentication (Simple OAuth module)
4. Build mobile UI that consumes the API
5. Add Firebase Cloud Messaging for push notifications

### Trade-offs

| Pros | Cons |
|------|------|
| Fastest path to mobile | Still maintaining Drupal |
| No feature parity work | API may need customization |
| Single source of truth | Two frontends to maintain |
| Existing team knowledge | Mobile devs need Drupal API knowledge |

### When to Choose This

- You want mobile ASAP
- AVC features are already working well
- Team knows Drupal but not full-stack mobile
- Budget/time constraints

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                        MOBILE APP                           │
│  ┌─────────────────┐  ┌─────────────────┐                  │
│  │   iOS (iPhone)  │  │ Android (Phone) │                  │
│  └────────┬────────┘  └────────┬────────┘                  │
│           │                    │                            │
│           └──────────┬─────────┘                            │
│                      │                                      │
│  ┌───────────────────▼───────────────────┐                 │
│  │         Flet / Flutter App            │                 │
│  │  • User Interface                     │                 │
│  │  • State Management                   │                 │
│  │  • Offline Cache                      │                 │
│  └───────────────────┬───────────────────┘                 │
└──────────────────────┼──────────────────────────────────────┘
                       │ HTTPS/WSS
┌──────────────────────┼──────────────────────────────────────┐
│                      │        BACKEND                       │
│  ┌───────────────────▼───────────────────┐                 │
│  │     Supabase / FastAPI                │                 │
│  │  • REST API                           │                 │
│  │  • Authentication                     │                 │
│  │  • Real-time Subscriptions            │                 │
│  └───────────────────┬───────────────────┘                 │
│                      │                                      │
│  ┌───────────────────▼───────────────────┐                 │
│  │         PostgreSQL Database           │                 │
│  │  • Users/Members                      │                 │
│  │  • Groups/Guilds                      │                 │
│  │  • Tasks/Workflows                    │                 │
│  │  • Assets/Documents                   │                 │
│  └───────────────────────────────────────┘                 │
│                                                             │
│  ┌───────────────────────────────────────┐                 │
│  │         File Storage (S3/Supabase)    │                 │
│  │  • Document uploads                   │                 │
│  │  • Profile images                     │                 │
│  └───────────────────────────────────────┘                 │
└─────────────────────────────────────────────────────────────┘
```

---

## Sources

### Mobile Frameworks
- [Flutter vs React Native 2025](https://www.thedroidsonroids.com/blog/flutter-vs-react-native-comparison)
- [FlutterFlow](https://flutterflow.io/)

### Python Mobile
- [Flet](https://flet.dev/)
- [BeeWare](https://beeware.org/)
- [Kivy vs BeeWare Comparison](https://thecyberiatech.com/blog/mobile-app/kivy-vs-beeware/)
- [Python Mobile Frameworks 2025](https://www.synapseindia.com/article/top-10-python-frameworks-for-mobile-app-development)

### Backend
- [FastAPI](https://fastapi.tiangolo.com/)
- [Django REST Framework](https://www.django-rest-framework.org/)
- [Supabase](https://supabase.com/)
- [Firebase vs Supabase 2025](https://zapier.com/blog/supabase-vs-firebase/)
- [Django Mobile Backend Best Practices](https://reintech.io/blog/django-mobile-app-backend-best-practices)

### Open Source Starters
- [Taskist - Flutter Todo](https://github.com/huextrat/Taskist)
- [Flutter Group Chat](https://github.com/RodrigoBertotti/flutter_group_chat_app_with_firebase)
- [Tasky Mobile App](https://github.com/RegNex/Tasky-Mobile-App)

### No-Code Platforms
- [Best No-Code App Builders 2025](https://www.adalo.com/posts/the-9-best-no-code-app-builders-2024)
- [Low-Code Platforms](https://thectoclub.com/tools/best-low-code-platform/)

### Django Packages for AVC Features
- [django-river](https://django-river.readthedocs.io/) - Workflow state machine
- [django-organizations](https://django-organizations.readthedocs.io/) - Multi-tenant organizations
- [django-notifications-hq](https://github.com/django-notifications/django-notifications) - Notifications
- [django-activity-stream](https://django-activity-stream.readthedocs.io/) - Activity feeds

---

## Final Conclusion

### The Bottom Line

| Goal | Best Choice |
|------|-------------|
| **Full AVC feature parity** | React Native + Django REST |
| **Fastest path to mobile** | Hybrid (Drupal backend + mobile frontend) |
| **100% Python (simplified)** | Flet + Supabase |
| **Best UI + Python backend** | Flutter + FastAPI |

### Decision Matrix

```
Do you need FULL AVC features?
├── YES → Do you want to keep Drupal?
│         ├── YES → Hybrid: Mobile frontend + Drupal JSON:API
│         └── NO  → React Native + Django REST (best packages)
│
└── NO (simplified is OK) → Do you require 100% Python?
                            ├── YES → Flet + Supabase
                            └── NO  → Flutter + FastAPI
```

### Key Takeaways

1. **AVC is complex** - No-code and simple frameworks can't handle it
2. **Django has the best packages** for workflow, notifications, activity streams
3. **Hybrid approach** is fastest if you want to keep existing Drupal work
4. **100% Python mobile** is possible but requires accepting limitations
5. **Expect 3-12 months** of development depending on feature scope

### Next Steps

1. **Define MVP features** - Which AVC features are essential for mobile?
2. **Choose approach** - Full rebuild vs hybrid vs simplified
3. **Prototype** - Build a small proof-of-concept with chosen stack
4. **Validate** - Test with real users before full development
