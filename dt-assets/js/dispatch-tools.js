jQuery(document).ready(function($) {

  _.forOwn( wpDispatchToolsSettings.diagnosis_results, ( value, key )=>{
    $(`#${key} .${ !value.has_problem ? 'invalid' : 'verified' }`).toggle()
  })
})
