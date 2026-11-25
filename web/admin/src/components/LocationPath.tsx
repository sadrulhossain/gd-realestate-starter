import { useQuery } from '@tanstack/react-query'
import { api } from '../api'
import { locationPaths, type LocationRow } from '../lib/locations'

/** "Country > City > Area" for a location id, from the (cached) flat location list. */
export function LocationPath({ id }: { id: unknown }) {
  const paths = useQuery({
    queryKey: ['locations-paths'],
    staleTime: 60_000,
    queryFn: async () => locationPaths(((await api.request('GET', '/v1/locations')) as { data: LocationRow[] }).data),
  })

  return <>{id ? (paths.data?.get(Number(id)) ?? '') : ''}</>
}
