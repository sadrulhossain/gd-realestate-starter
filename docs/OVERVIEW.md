# Product overview

A ready-to-launch website for a small business. Visitors get a fast public website in two languages; the business owner and their team manage every word and picture from a private admin area, with no developer needed for day-to-day changes.

## At a glance

| Part | What it is | Who uses it |
|------|------------|-------------|
| **Public website (portal)** | The business's website | Customers and visitors |
| **Admin area** | A private dashboard to edit all content | The owner and staff |
| **Backend (API)** | Stores the data, handles logins, sends emails | Works behind the scenes |

## What the client gets

- **A branded website** with their logo, favicon, colours, business name, contact details, opening hours and social links.
- **Two languages**: English plus one more of their choice (for example Bangla). Visitors switch language from the header; the second language has its own address (`/bn`).
- **An admin dashboard** to change all content without code, with an overview of what has been added and which messages are unread.
- **Staff accounts with roles**: the owner decides who can see and edit what (for example a writer who can only edit blog posts).
- **A contact inbox**: messages from the contact form arrive in the admin (and by email), can be read and marked as read.
- **Ready for search engines**: page titles and descriptions, social-sharing previews, a sitemap and structured data are produced automatically.
- **Works on phones**, tablets and desktops, for both the website and the admin.
- **Secure by default**: protected logins, permission checks on every action, spam protection on the contact form, safe image uploads. Review results are in [SECURITY.md](SECURITY.md).
- **Optional demo content** so the site looks complete on day one, then replaced with the client's own content.

## Website sections and pages

Pages visitors see (every one in both languages):

| Page / section | What it shows |
|----------------|---------------|
| **Home** | Hero banner, about summary, services, team, testimonials, gallery, FAQ and contact call-to-action, each can be switched on or off |
| **About us** | The business story and team |
| **Services** | List of services, each with its own detail page and optional price |
| **Pricing** | Per-service prices, hidden until the owner switches them on |
| **Gallery** | Photo gallery |
| **Testimonials** | Customer reviews |
| **Team** | Staff profiles with photos |
| **FAQ** | Questions and answers |
| **Blog** | Articles with categories, filtering and pages |
| **Contact** | Contact form, opening hours, map link, social links |
| **Privacy and Terms** | Legal pages |
| **Friendly 404** | A branded "page not found" |

## What the admin can manage

| Area | What the client can do |
|------|------------------------|
| **Settings** | Business name, logo, favicon, currency, contact details, hours, social links, in both languages |
| **Sections** | Show or hide homepage sections and drag them into the order they prefer |
| **Services** | Add, edit and remove services, with images and optional prices |
| **Team** | Staff profiles and photos |
| **Testimonials** | Customer quotes |
| **Gallery** | Upload many photos at once |
| **FAQs** | Questions and answers |
| **Blog** | Rich-text editor, cover image, categories, draft or published, SEO title and description |
| **Contact messages** | Read, review and mark messages as read |
| **Users and roles** | Invite staff, choose exactly what each role may do |
| **Menus** | Reorder what appears in the admin sidebar |
| **Profile** | Each user's own name and password |

Content changes appear on the public website straight away. Uploaded images are automatically converted to a light, fast format.

## Technology

| Layer | Technology |
|-------|------------|
| Public website | Next.js, Tailwind CSS |
| Admin area | React (Vite), Tailwind CSS, TipTap editor |
| Backend | Laravel (PHP) with OAuth 2 sign-in |
| Database | PostgreSQL 18.7 (cache, sessions, queue and captcha/throttle state in Redis 8.10.3) |

Exact versions: [VERSIONS.md](VERSIONS.md).

## Making it theirs

The look (colours, logo, texts, images) is set from the admin. Adding a new language, changing the brand palette or adding new kinds of content is a small code change by a developer: see [CUSTOMIZE.md](CUSTOMIZE.md).

## More information

| Topic | Document |
|-------|----------|
| Customising the product | [CUSTOMIZE.md](CUSTOMIZE.md) |
| Updating a running site | [UPGRADE.md](UPGRADE.md) |
| Security, accessibility and performance results | [SECURITY.md](SECURITY.md) |
