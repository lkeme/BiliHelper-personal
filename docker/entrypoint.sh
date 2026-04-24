#!/bin/sh
set -eu

APP_ROOT="/app"
CONFIG_PATH="${CONFIG_PATH:-${APP_ROOT}/profile/user/config/user.ini}"
PROFILE_ROOT="$(dirname "$(dirname "${CONFIG_PATH}")")"
EXAMPLE_PROFILE="${APP_ROOT}/profile/example"
INFO_LABEL="${Info:-[INFO]}"
FONT_RESET="${Font:-}"
GREEN_BG="${GreenBG:-}"
RED_BG="${RedBG:-}"

print_block() {
  printf '\n'
  printf ' ======== \n'
  printf ' %s %s %s \n' "${INFO_LABEL}" "$1" "${FONT_RESET}"
  printf ' ======== \n'
}

is_truthy() {
  value="$(printf '%s' "${1:-}" | tr '[:upper:]' '[:lower:]')"
  case "${value}" in
    1|true|yes|on)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

validate_branch() {
  case "${1}" in
    ''|*[!A-Za-z0-9._/-]*)
      print_block "${RED_BG} 无效的分支名称: ${1:-<empty>} ${FONT_RESET}"
      exit 1
      ;;
  esac
}

read_profile_branch() {
  if [ ! -f "${CONFIG_PATH}" ]; then
    return 0
  fi

  awk -F= '
    /^\[app\]/ { in_app=1; next }
    /^\[/ { in_app=0 }
    in_app && $1 ~ /^[[:space:]]*branch[[:space:]]*$/ {
      value=$2
      gsub(/^[[:space:]]+|[[:space:]]+$/, "", value)
      gsub(/"/, "", value)
      print value
      exit
    }
  ' "${CONFIG_PATH}"
}

resolve_branch() {
  if [ -n "${BRANCH:-}" ]; then
    echo "${BRANCH}"
    return
  fi

  profile_branch="$(read_profile_branch)"
  if [ -n "${profile_branch}" ]; then
    echo "${profile_branch}"
    return
  fi

  echo "master"
}

initialize_branch() {
  branch_name="$(resolve_branch)"
  validate_branch "${branch_name}"
  export BRANCH="${branch_name}"
  print_block "${GREEN_BG} 运行分支: ${branch_name} ${FONT_RESET}"
}

assert_profile_root_safe() {
  case "${CONFIG_PATH}" in
    *'..'*)
      print_block "${RED_BG} CONFIG_PATH 不允许包含 .. : ${CONFIG_PATH} ${FONT_RESET}"
      exit 1
      ;;
  esac

  case "${PROFILE_ROOT}" in
    "${APP_ROOT}/profile"|"${APP_ROOT}/profile/"|"/"|"/etc"|"/root"|"/app"|"/app/")
      print_block "${RED_BG} 非法的 profile 根目录: ${PROFILE_ROOT} ${FONT_RESET}"
      exit 1
      ;;
    "${APP_ROOT}/profile/"*)
      return
      ;;
    *)
      print_block "${RED_BG} profile 根目录必须位于 ${APP_ROOT}/profile 下: ${PROFILE_ROOT} ${FONT_RESET}"
      exit 1
      ;;
  esac
}

escape_ini_value() {
  printf '%s' "${1:-}" | tr -d '\r\n' | sed 's/\\/\\\\/g; s/"/\\"/g'
}

init_profile() {
  print_block "${GREEN_BG} 正在初始化 profile ${FONT_RESET}"
  assert_profile_root_safe

  if [ -f "${CONFIG_PATH}" ]; then
    print_block "${GREEN_BG} profile 已存在，跳过初始化 ${FONT_RESET}"
    return 0
  fi

  mkdir -p "$(dirname "${PROFILE_ROOT}")"
  rm -rf "${PROFILE_ROOT}"
  cp -r "${EXAMPLE_PROFILE}" "${PROFILE_ROOT}"

  escaped_user="$(escape_ini_value "${USER_NAME:-}")"
  escaped_password="$(escape_ini_value "${USER_PASSWORD:-}")"
  escaped_branch="$(escape_ini_value "${BRANCH}")"

  awk -v username="${escaped_user}" -v password="${escaped_password}" -v branch="${escaped_branch}" '
    BEGIN { section = "" }
    /^\[.*\]$/ {
      section = $0
      print
      next
    }
    section == "[login_account]" && $0 ~ /^username[[:space:]]*=/ {
      print "username = \"" username "\""
      next
    }
    section == "[login_account]" && $0 ~ /^password[[:space:]]*=/ {
      print "password = \"" password "\""
      next
    }
    section == "[app]" && $0 ~ /^branch[[:space:]]*=/ {
      print "branch = " branch
      next
    }
    { print }
  ' "${CONFIG_PATH}" > "${CONFIG_PATH}.tmp"
  mv "${CONFIG_PATH}.tmp" "${CONFIG_PATH}"

  print_block "${GREEN_BG} profile 初始化完成 ${FONT_RESET}"
}

require_profile() {
  assert_profile_root_safe

  if [ -f "${CONFIG_PATH}" ]; then
    return 0
  fi

  print_block "${RED_BG} profile configuration not found: ${CONFIG_PATH} ${FONT_RESET}"
  print_block "${RED_BG} run 'entrypoint.sh init_profile' first or mount an existing profile ${FONT_RESET}"
  exit 1
}

start_application() {
  if [ "${CAPTCHA:-0}" = "1" ]; then
    print_block "${GREEN_BG} 正在使用登录助手服务 ${FONT_RESET}"
    printf ' ======== \n'
    printf ' %s %s %s \n' "${INFO_LABEL}" "${GREEN_BG} 登录助手地址：http://${CAPTCHA_HOST}:${CAPTCHA_PORT} ${FONT_RESET}" "${FONT_RESET}"
    printf ' ======== \n'
    (
      cd "${APP_ROOT}/captcha"
      php -S "${CAPTCHA_HOST}:${CAPTCHA_PORT}"
    ) &
  fi

  if [ "$#" -gt 0 ]; then
    exec "$@"
  fi

  set -- php "${APP_ROOT}/app.php"

  if is_truthy "${RESET_CACHE:-0}"; then
    set -- "$@" --reset-cache
    if is_truthy "${PURGE_AUTH:-0}"; then
      set -- "$@" --purge-auth
      print_block "${GREEN_BG} Docker 启动参数: 附加 --reset-cache --purge-auth ${FONT_RESET}"
    else
      print_block "${GREEN_BG} Docker 启动参数: 附加 --reset-cache ${FONT_RESET}"
    fi
  elif is_truthy "${PURGE_AUTH:-0}"; then
    print_block "${RED_BG} PURGE_AUTH 已忽略：需与 RESET_CACHE=1 一起使用 ${FONT_RESET}"
  fi

  exec "$@"
}

main() {
  initialize_branch

  mode="${1:-run}"
  case "${mode}" in
    init_profile)
      shift || true
      init_profile
      ;;
    run)
      shift || true
      require_profile
      start_application "$@"
      ;;
    *)
      require_profile
      exec "$@"
      ;;
  esac
}

main "$@"
