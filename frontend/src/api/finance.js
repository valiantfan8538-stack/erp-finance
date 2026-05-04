import request from './request'

export function getSubjects(bookId) {
  return request.get('/finance/subjects', { params: { book_id: bookId } })
}

export function getSubjectTree(bookId) {
  return request.get('/finance/subjects/tree', { params: { book_id: bookId } })
}

export function createSubject(data) {
  return request.post('/finance/subjects', data)
}

export function updateSubject(id, data) {
  return request.put(`/finance/subjects/${id}`, data)
}

export function deleteSubject(id) {
  return request.delete(`/finance/subjects/${id}`)
}

export function getPeriods(bookId) {
  return request.get('/system/periods', { params: { book_id: bookId } })
}

export function closePeriod(id, data) {
  return request.post(`/system/periods/${id}/close`, data)
}

export function openPeriod(id, data) {
  return request.post(`/system/periods/${id}/open`, data)
}
