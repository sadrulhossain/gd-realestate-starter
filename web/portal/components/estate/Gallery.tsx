import type { PropertyMediaItem } from '../../lib/api'
import type { Messages } from '../../lib/i18n'
import { Picture } from '../Media'

const asMedia = (m: PropertyMediaItem) => m.file ?? (m.url ? { url: m.url, webp: {} } : null)

/**
 * Photos as a swipeable strip (CSS scroll-snap, no JavaScript). The strip is a focusable region, so keyboard users can
 * scroll it with the arrow keys. The first photo loads eagerly: it is the page's largest image. Without photos a tinted
 * panel stands in.
 */
export function Gallery({ media, title, messages }: { media: PropertyMediaItem[]; title: string; messages: Messages }) {
  const photos = media.filter((m) => m.kind === 'image').flatMap((m) => {
    const image = asMedia(m)

    return image ? [{ id: m.id, image }] : []
  })
  const t = messages.estate.detail

  if (photos.length === 0) {
    return (
      <div className="flex aspect-[16/9] items-center justify-center rounded-card bg-gradient-to-br from-brand-100 to-brand-200 text-brand-700">
        <span className="font-medium">{messages.estate.card.noPhoto}</span>
      </div>
    )
  }

  return (
    <div role="region" aria-label={t.gallery} tabIndex={0} className="flex snap-x snap-mandatory gap-3 overflow-x-auto rounded-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
      {photos.map(({ id, image }, i) => (
        <figure key={id} className="w-full shrink-0 snap-center overflow-hidden rounded-card bg-brand-50 md:w-[85%]">
          <Picture
            image={image}
            alt={`${title} - ${t.photo.replace('{n}', String(i + 1)).replace('{total}', String(photos.length))}`}
            sizes="(min-width: 1024px) 800px, 100vw"
            loading={i === 0 ? 'eager' : 'lazy'}
            className="aspect-[16/10] w-full object-cover"
          />
        </figure>
      ))}
    </div>
  )
}
