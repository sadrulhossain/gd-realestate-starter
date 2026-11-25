import type { CrudConfig } from './components/CrudPage'
import { Link } from 'react-router'
import { LeadPanel } from './components/LeadPanel'
import { LocationPath } from './components/LocationPath'
import { Money } from './components/Money'
import { PropertyStatusAction } from './components/PropertyStatusAction'
import { RowStatusMenu } from './components/RowStatusMenu'
import { StatusBadge } from './components/StatusBadge'
import { ToggleCell } from './components/ToggleCell'
import { useAuth } from './auth/store'
import { locationOptions, type LocationRow } from './lib/locations'
import { statusLabel } from './lib/propertyStatus'
import { LEAD_STATUSES, nextViewingStatuses, VIEWING_STATUSES } from './lib/salesStatus'

/** "faqs.view" -> row "faqs", column "view". */
export function permissionOption(name: string) {
  const [group = name, ...action] = name.split('.')

  return { value: name, label: action.join('.') || name, group }
}

/** Small preview for a row's `image` ({url} or null). */
const thumb = (r: Record<string, unknown>) => {
  const image = r.image as { url: string } | null | undefined

  return image ? <img src={image.url} alt="" className="h-10 w-14 rounded object-cover" /> : null
}

const yesNo = (value: unknown) => (value ? 'Yes' : 'No')

const LEAD_SOURCES = ['web', 'phone', 'walk_in', 'referral', 'other']

/** Local YYYY-MM-DD (the agenda starts today). */
const today = () => new Date().toLocaleDateString('en-CA')

const dateOnly = (value: unknown) => (value ? new Date(String(value)).toLocaleDateString('en', { dateStyle: 'medium' }) : '')
const dateTime = (value: unknown) => (value ? new Date(String(value)).toLocaleString('en', { dateStyle: 'medium', timeStyle: 'short' }) : '')
const timeOnly = (value: unknown) => (value ? new Date(String(value)).toLocaleTimeString('en', { timeStyle: 'short' }) : '')

/** Row link from a project to its units screen. */
function ProjectUnitsLink({ row }: { row: { id: number; name?: unknown } }) {
  const allowed = useAuth((s) => s.can)('project_units.view')

  return allowed ? (
    <Link className="text-sm text-primary-700 underline" to={`/projects/${row.id}/units`} aria-label={`Units of ${String(row.name)}`}>
      Units
    </Link>
  ) : null
}

/**
 * Admin modules are data. Each entry becomes a route (`path`, gated by `${key}.view`) and a CrudPage.
 * Sidebar entries come from the API (/me/menus), so a module also needs its menu row seeded.
 */
export const modules: CrudConfig[] = [
  {
    key: 'faqs',
    path: '/faqs',
    title: 'FAQs',
    noun: 'FAQ',
    endpoint: '/v1/faqs',
    rowLabel: (r) => String(r.question),
    columns: [
      { key: 'question', header: 'Question' },
      { key: 'sort_order', header: 'Order' },
      { key: 'is_visible', header: 'Visible', render: (r) => yesNo(r.is_visible) },
    ],
    fields: [
      { name: 'question', label: 'Question', bilingual: true, required: true },
      { name: 'answer', label: 'Answer', type: 'textarea', bilingual: true, required: true },
      { name: 'sort_order', label: 'Order', type: 'number', initial: 0 },
      { name: 'is_visible', label: 'Visible', type: 'checkbox', initial: true },
    ],
  },
  {
    key: 'pricing',
    path: '/pricing',
    title: 'Pricing',
    noun: 'Price',
    endpoint: '/v1/pricing',
    rowLabel: (r) => `${String((r.service as { title?: string } | null)?.title ?? '')} - ${String(r.name)}`,
    columns: [
      { key: 'service', header: 'Service', render: (r) => String((r.service as { title?: string } | null)?.title ?? '') },
      { key: 'name', header: 'Plan' },
      { key: 'price', header: 'Price' },
      { key: 'is_featured', header: 'Featured', render: (r) => yesNo(r.is_featured) },
      { key: 'is_visible', header: 'Visible', render: (r) => yesNo(r.is_visible) },
    ],
    fields: [
      { name: 'service_id', label: 'Service', type: 'select', required: true, optionsFrom: { endpoint: '/v1/services', map: (s: { id: number; title: string }) => ({ value: s.id, label: s.title }) } },
      { name: 'name', label: 'Plan name', bilingual: true, required: true },
      { name: 'price', label: 'Price', type: 'number', required: true, hint: 'The amount only. The currency is set in Settings.' },
      { name: 'unit', label: 'Per', bilingual: true, hint: 'For example per project or per month.' },
      { name: 'description', label: 'Short description', type: 'textarea', bilingual: true },
      { name: 'features', label: 'Features', type: 'textarea', bilingual: true, hint: 'One feature per line.' },
      { name: 'is_featured', label: 'Highlight this plan', type: 'checkbox', initial: false },
      { name: 'sort_order', label: 'Order', type: 'number', initial: 0 },
      { name: 'is_visible', label: 'Visible', type: 'checkbox', initial: true },
    ],
  },
  {
    key: 'services',
    image: true,
    path: '/services',
    title: 'Services',
    noun: 'Service',
    endpoint: '/v1/services',
    rowLabel: (r) => String(r.title),
    columns: [
      { key: 'image', header: 'Image', render: thumb },
      { key: 'title', header: 'Title' },
      { key: 'sort_order', header: 'Order' },
      { key: 'is_visible', header: 'Visible', render: (r) => yesNo(r.is_visible) },
    ],
    fields: [
      { name: 'title', label: 'Title', bilingual: true, required: true },
      { name: 'description', label: 'Description', type: 'textarea', bilingual: true },
      { name: 'sort_order', label: 'Order', type: 'number', initial: 0 },
      { name: 'is_visible', label: 'Visible', type: 'checkbox', initial: true },
    ],
  },
  {
    key: 'testimonials',
    image: true,
    path: '/testimonials',
    title: 'Testimonials',
    noun: 'Testimonial',
    endpoint: '/v1/testimonials',
    rowLabel: (r) => String(r.author_name),
    columns: [
      { key: 'image', header: 'Photo', render: thumb },
      { key: 'author_name', header: 'Author' },
      { key: 'quote', header: 'Quote' },
      { key: 'is_visible', header: 'Visible', render: (r) => yesNo(r.is_visible) },
    ],
    fields: [
      { name: 'author_name', label: 'Author', required: true },
      { name: 'author_role', label: 'Role', bilingual: true },
      { name: 'quote', label: 'Quote', type: 'textarea', bilingual: true, required: true },
      { name: 'sort_order', label: 'Order', type: 'number', initial: 0 },
      { name: 'is_visible', label: 'Visible', type: 'checkbox', initial: true },
    ],
  },
  {
    key: 'team',
    image: true,
    path: '/team',
    title: 'Team',
    noun: 'Team member',
    endpoint: '/v1/team',
    rowLabel: (r) => String(r.name),
    columns: [
      { key: 'image', header: 'Photo', render: thumb },
      { key: 'name', header: 'Name' },
      { key: 'role', header: 'Role' },
      { key: 'is_visible', header: 'Visible', render: (r) => yesNo(r.is_visible) },
    ],
    fields: [
      { name: 'name', label: 'Name', required: true },
      { name: 'role', label: 'Role', bilingual: true, required: true },
      { name: 'bio', label: 'Bio', type: 'textarea', bilingual: true },
      { name: 'sort_order', label: 'Order', type: 'number', initial: 0 },
      { name: 'is_visible', label: 'Visible', type: 'checkbox', initial: true },
    ],
  },
  {
    key: 'gallery',
    image: true,
    path: '/gallery',
    title: 'Gallery',
    noun: 'Gallery item',
    endpoint: '/v1/gallery',
    rowLabel: (r) => String(r.title ?? r.id),
    columns: [
      { key: 'image', header: 'Image', render: thumb },
      { key: 'title', header: 'Title' },
      { key: 'caption', header: 'Caption' },
      { key: 'is_visible', header: 'Visible', render: (r) => yesNo(r.is_visible) },
    ],
    fields: [
      { name: 'title', label: 'Title', bilingual: true },
      { name: 'caption', label: 'Caption', bilingual: true },
      { name: 'sort_order', label: 'Order', type: 'number', initial: 0 },
      { name: 'is_visible', label: 'Visible', type: 'checkbox', initial: true },
    ],
  },
  {
    // Draft/published is the API's `status`; leave "Publish at" empty to publish immediately.
    key: 'posts',
    path: '/posts',
    title: 'Posts',
    noun: 'Post',
    endpoint: '/v1/posts',
    image: true,
    rowLabel: (r) => String(r.title),
    columns: [
      { key: 'image', header: 'Cover', render: thumb },
      { key: 'title', header: 'Title' },
      { key: 'category', header: 'Category', render: (r) => String((r.category as { name?: string } | null)?.name ?? '') },
      { key: 'status', header: 'Status' },
    ],
    fields: [
      { name: 'title', label: 'Title', bilingual: true, required: true },
      { name: 'slug', label: 'Slug', required: true, hint: 'Lowercase letters, numbers and dashes. Used in the post URL.' },
      { name: 'category_id', label: 'Category', type: 'select', optionsFrom: { endpoint: '/v1/categories', map: (c: { id: number; name: string }) => ({ value: c.id, label: c.name }) } },
      { name: 'status', label: 'Status', type: 'select', initial: 'draft', options: [{ value: 'draft', label: 'Draft' }, { value: 'published', label: 'Published' }] },
      { name: 'published_at', label: 'Publish at', type: 'datetime', hint: 'Pick a date and time. Empty publishes immediately.' },
      { name: 'excerpt', label: 'Excerpt', type: 'textarea', bilingual: true },
      { name: 'body', label: 'Body', type: 'richtext', bilingual: true },
      { name: 'seo_title', label: 'SEO title', bilingual: true, hint: 'Up to 70 characters. Falls back to the title.' },
      { name: 'seo_description', label: 'SEO description', type: 'textarea', bilingual: true, hint: 'Up to 320 characters; about 155 shows fully in search results.' },
    ],
  },
  {
    key: 'categories',
    path: '/categories',
    title: 'Categories',
    noun: 'Category',
    endpoint: '/v1/categories',
    rowLabel: (r) => String(r.name),
    columns: [
      { key: 'name', header: 'Name' },
      { key: 'slug', header: 'Slug' },
      { key: 'sort_order', header: 'Order' },
    ],
    fields: [
      { name: 'name', label: 'Name', bilingual: true, required: true },
      { name: 'slug', label: 'Slug', required: true, hint: 'Lowercase letters, numbers and dashes.' },
      { name: 'sort_order', label: 'Order', type: 'number', initial: 0 },
    ],
  },
  {
    // Seeded and edit-only: the portal renders sections by key.
    key: 'sections',
    // Each section can carry an image (About shows it beside the text); the hero banner has its own Hero screen.
    image: true,
    path: '/sections',
    title: 'Sections',
    noun: 'Section',
    endpoint: '/v1/sections',
    canCreate: false,
    canDelete: false,
    // Drag a row to reorder; the visibility checkbox saves immediately.
    reorder: { endpoint: '/v1/sections/reorder' },
    rowLabel: (r) => String(r.title),
    columns: [
      { key: 'key', header: 'Key' },
      { key: 'title', header: 'Title' },
      {
        key: 'is_visible',
        header: 'Visible',
        render: (r) => <ToggleCell endpoint="/v1/sections" permissionKey="sections" row={r} field="is_visible" label={`Visible: ${String(r.title)}`} />,
      },
    ],
    fields: [
      { name: 'title', label: 'Title', bilingual: true, required: true },
      { name: 'content', label: 'Content', type: 'textarea', bilingual: true },
      { name: 'sort_order', label: 'Order', type: 'number', initial: 0 },
      { name: 'is_visible', label: 'Visible', type: 'checkbox', initial: false },
    ],
  },
  {
    key: 'users',
    path: '/users',
    title: 'Users',
    noun: 'User',
    endpoint: '/v1/users',
    rowLabel: (r) => String(r.email),
    columns: [
      { key: 'name', header: 'Name' },
      { key: 'email', header: 'Email' },
      { key: 'roles', header: 'Roles', render: (r) => (Array.isArray(r.roles) ? r.roles.join(', ') : '') },
    ],
    fields: [
      { name: 'name', label: 'Name', required: true },
      { name: 'email', label: 'Email', required: true },
      { name: 'password', label: 'Password', type: 'password', hint: 'At least 12 characters. When editing, leave blank to keep the current one.' },
      { name: 'roles', label: 'Roles', type: 'checkgroup', optionsFrom: { endpoint: '/v1/roles', map: (role: { name: string }) => ({ value: role.name, label: role.name }) } },
    ],
  },
  {
    // The matrix: one row per module, one column per action, built from /v1/permissions ("faqs.view" -> row faqs, column view).
    key: 'roles',
    path: '/roles',
    title: 'Roles',
    noun: 'Role',
    endpoint: '/v1/roles',
    rowLabel: (r) => String(r.name),
    columns: [
      { key: 'name', header: 'Name' },
      { key: 'permissions', header: 'Permissions', render: (r) => (Array.isArray(r.permissions) ? r.permissions.length : 0) },
    ],
    fields: [
      { name: 'name', label: 'Name', required: true, hint: 'Lowercase letters, numbers, dashes and underscores.' },
      { name: 'permissions', label: 'Permissions', type: 'checkgroup', optionsFrom: { endpoint: '/v1/permissions', map: permissionOption } },
    ],
  },
  {
    // Seeded tree, edit-only. Rows arrive depth-first; drag reorders within the same parent.
    key: 'menus',
    path: '/menus',
    title: 'Menus',
    noun: 'Menu entry',
    endpoint: '/v1/menus',
    canCreate: false,
    canDelete: false,
    reorder: { endpoint: '/v1/menus/reorder', group: (r) => r.parent_id ?? null },
    rowLabel: (r) => String(r.title),
    columns: [
      { key: 'title', header: 'Title', render: (r) => <span style={{ paddingLeft: `${Number(r.depth ?? 0) * 1.5}rem` }}>{String(r.title)}</span> },
      { key: 'path', header: 'Path' },
      { key: 'permission_name', header: 'Permission' },
    ],
    fields: [
      { name: 'title', label: 'Title', required: true },
      { name: 'icon', label: 'Icon', type: 'icon', hint: 'Shown beside the menu entry in the sidebar.' },
    ],
  },
  // --- Real estate catalogue (R5) ---
  {
    // Flat list with parent_id (country > city > area); the Path column and the parent select show the whole chain.
    key: 'locations',
    path: '/locations',
    title: 'Locations',
    noun: 'Location',
    endpoint: '/v1/locations',
    rowLabel: (r) => String(r.name),
    columns: [
      { key: 'name', header: 'Name' },
      { key: 'path', header: 'Path', render: (r) => <LocationPath id={r.id} /> },
      { key: 'sort_order', header: 'Order' },
      { key: 'is_active', header: 'Active', render: (r) => yesNo(r.is_active) },
    ],
    fields: [
      { name: 'name', label: 'Name', bilingual: true, required: true },
      { name: 'slug', label: 'Slug', required: true, hint: 'Lowercase letters, numbers and dashes. Unique among locations with the same parent.' },
      {
        name: 'parent_id',
        label: 'Parent',
        type: 'select',
        hint: 'Empty for a country. At most three levels: country, city, area.',
        optionsFrom: { endpoint: '/v1/locations', mapAll: (items: LocationRow[]) => locationOptions(items) as never },
      },
      { name: 'lat', label: 'Latitude', type: 'number' },
      { name: 'lng', label: 'Longitude', type: 'number' },
      { name: 'sort_order', label: 'Order', type: 'number', initial: 0 },
      { name: 'is_active', label: 'Active', type: 'checkbox', initial: true },
    ],
  },
  {
    key: 'property_types',
    path: '/property-types',
    title: 'Property types',
    noun: 'Property type',
    endpoint: '/v1/property-types',
    rowLabel: (r) => String(r.name),
    columns: [
      { key: 'name', header: 'Name' },
      { key: 'group', header: 'Group', render: (r) => statusLabel(String(r.group)) },
      { key: 'sort_order', header: 'Order' },
      { key: 'is_active', header: 'Active', render: (r) => yesNo(r.is_active) },
    ],
    fields: [
      { name: 'name', label: 'Name', bilingual: true, required: true },
      { name: 'key', label: 'Key', required: true, hint: 'Lowercase letters, numbers and dashes. Used in URLs and filters.' },
      { name: 'group', label: 'Group', type: 'select', required: true, initial: 'residential', options: ['residential', 'commercial', 'land'].map((g) => ({ value: g, label: statusLabel(g) })) },
      { name: 'sort_order', label: 'Order', type: 'number', initial: 0 },
      { name: 'is_active', label: 'Active', type: 'checkbox', initial: true },
    ],
  },
  {
    key: 'amenities',
    path: '/amenities',
    title: 'Amenities',
    noun: 'Amenity',
    endpoint: '/v1/amenities',
    rowLabel: (r) => String(r.name),
    columns: [
      { key: 'name', header: 'Name' },
      { key: 'group', header: 'Group' },
      { key: 'sort_order', header: 'Order' },
      { key: 'is_active', header: 'Active', render: (r) => yesNo(r.is_active) },
    ],
    fields: [
      { name: 'name', label: 'Name', bilingual: true, required: true },
      { name: 'group', label: 'Group', hint: 'For example Building, Security or Comfort.' },
      { name: 'icon', label: 'Icon', type: 'icon' },
      { name: 'sort_order', label: 'Order', type: 'number', initial: 0 },
      { name: 'is_active', label: 'Active', type: 'checkbox', initial: true },
    ],
  },
  {
    // Status never goes through the form: the row menu uses POST /v1/properties/{id}/status.
    key: 'properties',
    path: '/properties',
    title: 'Properties',
    noun: 'Property',
    endpoint: '/v1/properties',
    loadOnEdit: true,
    rowLabel: (r) => `${String(r.ref_code)} ${String(r.title)}`,
    rowExtra: (r) => <PropertyStatusAction row={r} />,
    columns: [
      { key: 'ref_code', header: 'Ref' },
      { key: 'title', header: 'Title' },
      { key: 'listing_type', header: 'For', render: (r) => statusLabel(String(r.listing_type)) },
      { key: 'location', header: 'Location', render: (r) => String((r.location as { name?: string } | null)?.name ?? '') },
      { key: 'price', header: 'Price', render: (r) => <Money minor={r.price} /> },
      { key: 'status', header: 'Status', render: (r) => statusLabel(String(r.status)) },
    ],
    fields: [
      { tab: 'Basics', name: 'title', label: 'Title', bilingual: true, required: true },
      { tab: 'Basics', name: 'listing_type', label: 'Listing type', type: 'select', required: true, initial: 'sale', options: [{ value: 'sale', label: 'For sale' }, { value: 'rent', label: 'For rent' }] },
      { tab: 'Basics', name: 'rent_period', label: 'Rent period', type: 'select', hint: 'Required for rent listings, leave empty for sale.', options: ['month', 'year', 'day'].map((p) => ({ value: p, label: statusLabel(p) })) },
      { tab: 'Basics', name: 'price', label: 'Price', type: 'money', required: true },
      { tab: 'Basics', name: 'property_type_id', label: 'Property type', type: 'select', required: true, optionsFrom: { endpoint: '/v1/property-types', map: (t: { id: number; name: string }) => ({ value: t.id, label: t.name }) } },
      { tab: 'Basics', name: 'location_id', label: 'Location', type: 'select', required: true, optionsFrom: { endpoint: '/v1/locations', mapAll: (items: LocationRow[]) => locationOptions(items) as never } },
      { tab: 'Basics', name: 'agent_id', label: 'Agent', type: 'select', hint: 'Shows active agents.', optionsFrom: { endpoint: '/v1/public/agents', map: (a: { id: number; name: string }) => ({ value: a.id, label: a.name }) } },
      { tab: 'Basics', name: 'featured', label: 'Featured', type: 'checkbox', initial: false },
      { tab: 'Details', name: 'area', label: 'Area', type: 'number' },
      { tab: 'Details', name: 'area_unit', label: 'Area unit', type: 'select', initial: 'sqft', options: [{ value: 'sqft', label: 'sq ft' }, { value: 'sqm', label: 'sq m' }] },
      { tab: 'Details', name: 'beds', label: 'Bedrooms', type: 'number' },
      { tab: 'Details', name: 'baths', label: 'Bathrooms', type: 'number' },
      { tab: 'Details', name: 'floor', label: 'Floor', type: 'number' },
      { tab: 'Details', name: 'total_floors', label: 'Floors in building', type: 'number' },
      { tab: 'Details', name: 'facing', label: 'Facing', type: 'select', options: ['north', 'south', 'east', 'west', 'north_east', 'north_west', 'south_east', 'south_west'].map((f) => ({ value: f, label: statusLabel(f) })) },
      { tab: 'Details', name: 'year_built', label: 'Year built', type: 'number' },
      { tab: 'Details', name: 'furnished', label: 'Furnishing', type: 'select', options: ['unfurnished', 'semi_furnished', 'furnished'].map((f) => ({ value: f, label: statusLabel(f) })) },
      { tab: 'Description', name: 'description', label: 'Description', type: 'textarea', bilingual: true },
      { tab: 'Place', name: 'address_line', label: 'Address' },
      { tab: 'Place', name: 'lat', label: 'Latitude', type: 'number', hint: 'Needs a longitude too. Powers the radius search.' },
      { tab: 'Place', name: 'lng', label: 'Longitude', type: 'number' },
      { tab: 'Amenities', name: 'amenity_ids', label: 'Amenities', type: 'checkgroup', optionsFrom: { endpoint: '/v1/amenities', map: (a: { id: number; name: string }) => ({ value: a.id, label: a.name }) } },
      { tab: 'SEO', name: 'seo_title', label: 'SEO title', hint: 'Falls back to the title.' },
      { tab: 'SEO', name: 'seo_description', label: 'SEO description', type: 'textarea', hint: 'Up to 320 characters.' },
    ],
  },
  {
    key: 'developers',
    path: '/developers',
    title: 'Developers',
    noun: 'Developer',
    endpoint: '/v1/developers',
    rowLabel: (r) => String(r.name),
    columns: [
      { key: 'name', header: 'Name' },
      { key: 'website', header: 'Website' },
      { key: 'sort_order', header: 'Order' },
      { key: 'is_active', header: 'Active', render: (r) => yesNo(r.is_active) },
    ],
    fields: [
      { name: 'name', label: 'Name', bilingual: true, required: true },
      { name: 'description', label: 'Description', type: 'textarea', bilingual: true },
      { name: 'website', label: 'Website', hint: 'A full address starting with https://.' },
      { name: 'email', label: 'Email' },
      { name: 'phone', label: 'Phone' },
      { name: 'sort_order', label: 'Order', type: 'number', initial: 0 },
      { name: 'is_active', label: 'Active', type: 'checkbox', initial: true },
    ],
  },
  {
    // Units live on their own screen (/projects/:id/units, see unitsConfig); the row link opens it.
    key: 'projects',
    path: '/projects',
    title: 'Projects',
    noun: 'Project',
    endpoint: '/v1/projects',
    rowLabel: (r) => String(r.name),
    rowExtra: (r) => <ProjectUnitsLink row={r} />,
    columns: [
      { key: 'name', header: 'Name' },
      { key: 'developer', header: 'Developer', render: (r) => String((r.developer as { name?: string } | null)?.name ?? '') },
      { key: 'status', header: 'Status', render: (r) => statusLabel(String(r.status)) },
      { key: 'units_count', header: 'Units' },
      { key: 'is_active', header: 'Active', render: (r) => yesNo(r.is_active) },
    ],
    fields: [
      { tab: 'Basics', name: 'name', label: 'Name', bilingual: true, required: true },
      { tab: 'Basics', name: 'developer_id', label: 'Developer', type: 'select', optionsFrom: { endpoint: '/v1/developers', map: (d: { id: number; name: string }) => ({ value: d.id, label: d.name }) } },
      { tab: 'Basics', name: 'status', label: 'Status', type: 'select', initial: 'upcoming', options: ['upcoming', 'under_construction', 'completed'].map((s) => ({ value: s, label: statusLabel(s) })) },
      { tab: 'Basics', name: 'handover_date', label: 'Handover date', type: 'date' },
      { tab: 'Basics', name: 'featured', label: 'Featured', type: 'checkbox', initial: false },
      { tab: 'Basics', name: 'is_active', label: 'Active', type: 'checkbox', initial: true },
      { tab: 'Description', name: 'description', label: 'Description', type: 'textarea', bilingual: true },
      { tab: 'Place', name: 'location_id', label: 'Location', type: 'select', required: true, optionsFrom: { endpoint: '/v1/locations', mapAll: (items: LocationRow[]) => locationOptions(items) as never } },
      { tab: 'Place', name: 'address_line', label: 'Address' },
      { tab: 'Place', name: 'lat', label: 'Latitude', type: 'number' },
      { tab: 'Place', name: 'lng', label: 'Longitude', type: 'number' },
      { tab: 'SEO', name: 'seo_title', label: 'SEO title' },
      { tab: 'SEO', name: 'seo_description', label: 'SEO description', type: 'textarea' },
    ],
  },
  // --- Sales (R6) ---
  {
    // Staff profile shown on the portal. Linking a user turns on the own-records scope for that user.
    key: 'agents',
    image: true,
    path: '/agents',
    title: 'Agents',
    noun: 'Agent',
    endpoint: '/v1/agents',
    rowLabel: (r) => String(r.name),
    columns: [
      { key: 'image', header: 'Photo', render: thumb },
      { key: 'name', header: 'Name' },
      { key: 'title', header: 'Title' },
      { key: 'phone', header: 'Phone' },
      { key: 'email', header: 'Email' },
      { key: 'is_active', header: 'Active', render: (r) => yesNo(r.is_active) },
    ],
    fields: [
      { name: 'name', label: 'Name', required: true },
      { name: 'title', label: 'Job title', bilingual: true },
      { name: 'bio', label: 'Bio', type: 'textarea', bilingual: true },
      { name: 'email', label: 'Email' },
      { name: 'phone', label: 'Phone' },
      { name: 'whatsapp', label: 'WhatsApp' },
      { name: 'license_no', label: 'Licence number' },
      {
        name: 'user_id',
        label: 'Staff login',
        type: 'select',
        needs: 'users.view',
        hint: 'Links this agent to a user, who then sees only their own leads and viewings. Shows the first page of users.',
        optionsFrom: { endpoint: '/v1/users', map: (u: { id: number; name: string; email: string }) => ({ value: u.id, label: `${u.name} (${u.email})` }) },
      },
      { name: 'sort_order', label: 'Order', type: 'number', initial: 0 },
      { name: 'is_active', label: 'Active', type: 'checkbox', initial: true },
    ],
  },
  {
    // Status and assignment are not form fields: the edit dialog carries the pipeline panel (LeadPanel).
    key: 'leads',
    path: '/leads',
    title: 'Leads',
    noun: 'Lead',
    endpoint: '/v1/leads',
    rowLabel: (r) => String(r.name),
    filters: [
      { name: 'q', label: 'Search', type: 'text' },
      { name: 'status', label: 'Status', type: 'select', options: LEAD_STATUSES.map((s) => ({ value: s, label: statusLabel(s) })) },
      { name: 'source', label: 'Source', type: 'select', options: LEAD_SOURCES.map((s) => ({ value: s, label: statusLabel(s) })) },
      { name: 'agent_id', label: 'Agent', type: 'select', optionsFrom: { endpoint: '/v1/agents', map: (a: { id: number; name: string }) => ({ value: a.id, label: a.name }) } },
    ],
    columns: [
      { key: 'name', header: 'Name' },
      { key: 'contact', header: 'Contact', render: (r) => String(r.email ?? r.phone ?? '') },
      { key: 'source', header: 'Source', render: (r) => statusLabel(String(r.source)) },
      { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
      { key: 'agent', header: 'Agent', render: (r) => String((r.agent as { name?: string } | null)?.name ?? '') },
      { key: 'created_at', header: 'Received', render: (r) => dateOnly(r.created_at) },
    ],
    fields: [
      { name: 'name', label: 'Name', required: true },
      { name: 'email', label: 'Email', hint: 'An email or a phone number is required.' },
      { name: 'phone', label: 'Phone' },
      { name: 'message', label: 'Message', type: 'textarea' },
      { name: 'source', label: 'Source', type: 'select', initial: 'phone', options: LEAD_SOURCES.map((s) => ({ value: s, label: statusLabel(s) })) },
      {
        name: 'agent_id',
        label: 'Agent',
        type: 'select',
        createOnly: true,
        needs: 'leads.assign',
        hint: 'Empty leaves the lead unassigned. Change it later in the pipeline panel.',
        optionsFrom: { endpoint: '/v1/agents', map: (a: { id: number; name: string }) => ({ value: a.id, label: a.name }) },
      },
    ],
    editExtra: (r) => <LeadPanel leadId={r.id} />,
  },
  {
    // The agenda: earliest first, from today by default. Reschedule in the edit dialog, change status from the row menu.
    key: 'viewings',
    path: '/viewings',
    title: 'Viewings',
    noun: 'Viewing',
    endpoint: '/v1/viewings',
    rowLabel: (r) => `${String((r.property as { title?: string } | null)?.title ?? 'viewing')} on ${dateTime(r.starts_at)}`,
    rowExtra: (r) => (
      <RowStatusMenu
        label={`Change status of the viewing on ${dateTime(r.starts_at)}`}
        endpoint={`/v1/viewings/${r.id}/status`}
        listEndpoint="/v1/viewings"
        permission="viewings.update"
        moves={nextViewingStatuses(String(r.status))}
      />
    ),
    filters: [
      { name: 'from', label: 'From', type: 'date', initial: today() },
      { name: 'to', label: 'To', type: 'date' },
      { name: 'status', label: 'Status', type: 'select', options: VIEWING_STATUSES.map((s) => ({ value: s, label: statusLabel(s) })) },
      { name: 'agent_id', label: 'Agent', type: 'select', optionsFrom: { endpoint: '/v1/agents', map: (a: { id: number; name: string }) => ({ value: a.id, label: a.name }) } },
    ],
    columns: [
      { key: 'starts_at', header: 'When', render: (r) => `${dateTime(r.starts_at)} - ${timeOnly(r.ends_at)}` },
      { key: 'property', header: 'Property', render: (r) => String((r.property as { ref_code?: string; title?: string } | null)?.title ?? '') },
      { key: 'lead', header: 'Lead', render: (r) => String((r.lead as { name?: string } | null)?.name ?? '') },
      { key: 'agent', header: 'Agent', render: (r) => String((r.agent as { name?: string } | null)?.name ?? '') },
      { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    ],
    fields: [
      {
        name: 'property_id',
        label: 'Property',
        required: true,
        createOnly: true,
        hint: 'Search by title or reference code. The property cannot be changed later: cancel and book again.',
        lookup: { endpoint: '/v1/properties', label: (p: { ref_code: string; title: string }) => `${p.ref_code} ${p.title}` },
      },
      {
        name: 'lead_id',
        label: 'Lead',
        lookup: { endpoint: '/v1/leads', label: (l: { name: string }) => l.name, initialLabel: (v) => (v.lead as { name?: string } | null)?.name },
      },
      {
        name: 'agent_id',
        label: 'Agent',
        type: 'select',
        createOnly: true,
        needs: 'viewings.view_all',
        hint: 'Empty uses the property agent. Agents book for themselves.',
        optionsFrom: { endpoint: '/v1/agents', map: (a: { id: number; name: string }) => ({ value: a.id, label: a.name }) },
      },
      { name: 'starts_at', label: 'Starts', type: 'datetime', required: true },
      { name: 'ends_at', label: 'Ends', type: 'datetime', required: true, hint: 'At most 8 hours after the start. The agent must be free.' },
      { name: 'notes', label: 'Notes', type: 'textarea' },
    ],
  },
]
