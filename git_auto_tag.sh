#!/bin/bash

# Git 自动递增版本号并打 Tag 脚本（自动删除上一次 Tag）
# 版本规则：v<major>.<minor>.<patch>（默认递增 patch，如 v1.0.0 → v1.0.1）
# 用法：./git_auto_tag.sh ["major"|"minor"]  # 可选参数指定递增 major 或 minor

set -e  # 遇到错误立即退出

# 获取最新 Tag
LATEST_TAG=$(git describe --tags --abbrev=0 2>/dev/null || echo "v0.0.0")
echo "当前最新 Tag: $LATEST_TAG"

# 解析版本号（格式：v<major>.<minor>.<patch>）
if [[ $LATEST_TAG =~ ^v([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
    MAJOR=${BASH_REMATCH[1]}
    MINOR=${BASH_REMATCH[2]}
    PATCH=${BASH_REMATCH[3]}
else
    echo "错误：Tag 格式必须为 v<major>.<minor>.<patch>（例如 v1.0.0）"
    exit 1
fi

# === 新增：删除上一次 Tag（本地和远程） ===
if [ "$LATEST_TAG" != "v0.0.0" ]; then
    echo "正在删除上一次 Tag: $LATEST_TAG..."
    git tag -d "$LATEST_TAG"  # 删除本地
    git push origin :refs/tags/"$LATEST_TAG"  # 删除远程
fi

# 根据参数递增版本号
if [ "$1" = "major" ]; then
    MAJOR=$((MAJOR + 1))
    MINOR=0
    PATCH=0
elif [ "$1" = "minor" ]; then
    MINOR=$((MINOR + 1))
    PATCH=0
else
    PATCH=$((PATCH + 1))  # 默认递增 patch
fi

NEW_TAG="v${MAJOR}.${MINOR}.${PATCH}"
TAG_MESSAGE="Release $NEW_TAG"

# 检查 Git 状态
if [ -n "$(git status --porcelain)" ]; then
    echo "检测到未提交的更改，正在自动提交..."
    git add .
    git commit -m "Auto commit before tagging $NEW_TAG"
else
    echo "没有未提交的更改。"
fi

# 创建 Tag（带注释）
echo "正在创建新 Tag: $NEW_TAG..."
git tag -a "$NEW_TAG" -m "$TAG_MESSAGE"

# 推送 Tag 到远程
echo "正在推送 Tag 到远程仓库..."
git push origin "$NEW_TAG"

echo "✅ 成功创建并推送 Tag: $NEW_TAG"