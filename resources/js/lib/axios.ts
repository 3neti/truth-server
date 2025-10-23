import axios from 'axios'

// Configure axios to include CSRF token from meta tag
const token = document.head.querySelector('meta[name="csrf-token"]')

if (token) {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content')
} else {
  console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token')
}

// Configure axios to send credentials (cookies) with requests
axios.defaults.withCredentials = true
axios.defaults.withXSRFToken = true

export default axios
