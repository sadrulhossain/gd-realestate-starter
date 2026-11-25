import { Link, useParams } from 'react-router'
import { CrudPage } from '../components/CrudPage'
import { unitsConfig } from '../unitsConfig'

/** The units of one project: the plain CrudPage pointed at `/v1/projects/{id}/units` (rows live at `/v1/units/{id}`). */
export function ProjectUnitsPage() {
  const { id = '' } = useParams()

  return (
    <div className="space-y-4">
      <Link to="/projects" className="text-sm text-primary-700 underline">
        Back to projects
      </Link>
      <CrudPage key={id} config={unitsConfig(id)} />
    </div>
  )
}
