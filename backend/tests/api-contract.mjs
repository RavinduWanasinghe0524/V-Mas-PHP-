import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const api = fs.readFileSync(path.join(root, 'V-Mas Frontend/src/services/api.js'), 'utf8')
const src = path.join(root, 'backend/src')
const php = fs.readdirSync(src).filter(name => name.endsWith('.php')).map(name => fs.readFileSync(path.join(src, name), 'utf8')).join('\n')

const expected = []
const calls = /api\.(get|post|put|patch|delete)\(\s*(?:'([^']+)'|`([^`]+)`)/g
for (const match of api.matchAll(calls)) expected.push([match[1].toUpperCase(), '/api' + (match[2] || match[3])])

const implemented = []
const routes = /route\(\s*['"]([A-Z]+)['"],\s*['"]([^'"]+)['"]/g
for (const match of php.matchAll(routes)) implemented.push([match[1], match[2]])

function shape(value) {
  return value.replace(/\$\{[^}]+\}|\$[a-zA-Z]+|\{[^}]+\}/g, '*')
}
const missing = expected.filter(([method, endpoint]) => {
  const target = shape(endpoint)
  return !implemented.some(([routeMethod, routePath]) => {
    const pattern = '^' + shape(routePath).split('/').map(segment => segment === '*' ? '[^/]+' : segment.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('/') + '$'
    return routeMethod === method && new RegExp(pattern).test(target)
  })
})
console.log(`${expected.length} frontend calls, ${implemented.length} PHP route declarations`)
for (const [method, endpoint] of missing) console.error(`Missing: ${method} ${endpoint}`)
if (missing.length) process.exitCode = 1
