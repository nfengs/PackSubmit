<?php
declare (strict_types = 1);

namespace app\controller;

use app\BaseController;
use app\exception\BizException;
use app\model\Attachment as AttachmentModel;
use app\model\PackProposal;
use app\model\User;
use app\service\AuditService;
use app\service\ProposalService;
use think\facade\Db;
use think\response\File;
use think\response\Json;

/**
 * 方案附件控制器
 */
class Attachment extends BaseController
{
    /** 允许上传的扩展名 */
    protected const ALLOW_EXT = ['pdf', 'dwg', 'xlsx', 'docx', 'png', 'jpg', 'jpeg'];

    /** 单文件大小限制：10MB */
    protected const MAX_SIZE = 10 * 1024 * 1024;

    /** 允许在线预览的扩展名 */
    protected const PREVIEW_EXT = ['pdf', 'png', 'jpg', 'jpeg'];

    /**
     * 上传附件（支持暂存，proposal_id 可选）
     * @return Json
     * @throws BizException
     */
    public function upload(): Json
    {
        $user = $this->currentUser();

        if ((int) $user->role === User::ROLE_REVIEWER) {
            throw new BizException('评审员不能上传附件', 403, 403);
        }

        $file = $this->request->file('file');

        if (!$file) {
            throw new BizException('请选择要上传的文件');
        }

        $ext = strtolower($file->getOriginalExtension());

        if (!in_array($ext, self::ALLOW_EXT, true)) {
            throw new BizException('仅支持 pdf、dwg、xlsx、docx、png、jpg 格式');
        }

        if ((int) $file->getSize() > self::MAX_SIZE) {
            throw new BizException('单个文件不能超过 10MB');
        }

        $proposalId = (int) $this->request->param('proposal_id', 0);
        $proposal   = null;

        $attachType = (string) $this->request->param('attach_type', AttachmentModel::TYPE_ATTACHMENT);

        if (!isset(AttachmentModel::TYPE_TEXT[$attachType])) {
            throw new BizException('附件类型不正确');
        }

        // 图片类附件（零件图/内部排列/整体包装/包装图片）只允许图片格式
        if (in_array($attachType, AttachmentModel::IMAGE_TYPES, true)
            && !in_array($ext, AttachmentModel::IMAGE_EXT, true)) {
            throw new BizException('图片分类仅支持上传 png、jpg 图片');
        }

        // 图片后缀的文件必须是真实图片：按文件内容识别，扩展名与内容不符时以真实格式为准
        $imageMime = null;

        if (in_array($ext, AttachmentModel::IMAGE_EXT, true)) {
            $image = AttachmentModel::detectImage($file->getPathname());

            if ($image === null) {
                throw new BizException('文件内容不是有效的图片，仅支持 png、jpg 格式');
            }

            $ext       = $image['ext'];
            $imageMime = $image['mime'];
        }

        if ($proposalId > 0) {
            $proposal = PackProposal::find($proposalId);

            if (!$proposal) {
                throw new BizException('方案不存在或已被删除', 0, 404);
            }

            // 仅方案所属人/管理员且状态可编辑时允许上传
            ProposalService::assertEditable($proposal, $user);

            // 每个图片位置只允许 1 张
            if (in_array($attachType, AttachmentModel::IMAGE_TYPES, true)) {
                $exists = AttachmentModel::where('proposal_id', $proposalId)
                    ->where('attach_type', $attachType)
                    ->count();

                if ($exists > 0) {
                    throw new BizException(
                        AttachmentModel::TYPE_TEXT[$attachType] . '只能上传 1 张图片，请先删除原图片'
                    );
                }
            }
        }

        $relativeDir = AttachmentModel::STORAGE_DIR . '/' . date('Ym');
        $saveDir     = AttachmentModel::diskPath($relativeDir);

        // 移动前读取文件元信息与内容 MD5（move 后临时文件已不存在）
        $originalName = $file->getOriginalName();
        $fileSize     = (int) $file->getSize();
        // 图片使用检测出的真实 MIME，普通文件沿用上传信息
        $mimeType     = $imageMime ?? (string) $file->getMime();
        $md5          = md5_file($file->getPathname());
        $md5          = is_string($md5) ? $md5 : '';

        // 去重：内容相同的文件只保留一份物理文件，新记录复用已有路径
        $reused       = false;
        $relativePath = '';

        if ($md5 !== '') {
            // 取第一条物理文件仍存在的记录复用（历史记录的文件可能已被清理）
            $candidates = AttachmentModel::where('md5', $md5)->order('id', 'asc')->select();

            foreach ($candidates as $candidate) {
                $existingPath = AttachmentModel::diskPath((string) $candidate->file_path);

                if (is_file($existingPath)) {
                    $reused       = true;
                    $relativePath = (string) $candidate->file_path;
                    break;
                }
            }
        }

        if (!$reused) {
            if (!is_dir($saveDir) && !mkdir($saveDir, 0755, true) && !is_dir($saveDir)) {
                throw new BizException('上传目录创建失败');
            }

            $fileName     = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $file->move($saveDir, $fileName);
            $relativePath = $relativeDir . '/' . $fileName;
        }

        $attachment = AttachmentModel::create([
            'proposal_id' => $proposalId,
            'attach_type' => $attachType,
            'user_id'     => (int) $user->id,
            'file_name'   => $originalName,
            'file_path'   => $relativePath,
            'file_ext'    => $ext,
            'file_size'   => $fileSize,
            'mime_type'   => $mimeType,
            'md5'         => $md5,
        ]);

        if ($proposal) {
            AuditService::record((int) $user->id, 'upload', $proposalId, [
                'version' => (int) $proposal->version,
                'content' => '上传附件：' . $attachment->file_name . ($reused ? '（复用已有文件）' : ''),
            ]);
        }

        $data             = $attachment->toArray();
        $data['reused']   = $reused;

        return $this->success($data, $reused ? '上传成功（已复用服务器上的相同文件）' : '上传成功');
    }

    /**
     * 附件下载（鉴权）
     * @param int $id
     * @return File
     * @throws BizException
     */
    public function download(int $id): File
    {
        $attachment = $this->findAttachment($id);
        $path       = $this->absolutePath($attachment);

        return download($path, $attachment->file_name);
    }

    /**
     * 附件在线预览（pdf/png/jpg）
     * @param int $id
     * @return \think\Response
     * @throws BizException
     */
    public function preview(int $id): \think\Response
    {
        $attachment = $this->findAttachment($id);

        if (!in_array(strtolower((string) $attachment->file_ext), self::PREVIEW_EXT, true)) {
            throw new BizException('该类型文件不支持在线预览，请下载后查看');
        }

        $path    = $this->absolutePath($attachment);
        $content = file_get_contents($path);

        if ($content === false) {
            throw new BizException('文件读取失败');
        }

        return response($content)
            ->contentType((string) ($attachment->mime_type ?: 'application/octet-stream'))
            ->header([
                'Content-Disposition' => "inline; filename*=UTF-8''" . rawurlencode((string) $attachment->file_name),
            ]);
    }

    /**
     * 删除附件
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \Throwable
     */
    public function delete(int $id): Json
    {
        // 物理文件可能已被清理，删除记录本身不受影响
        $attachment = $this->findAttachment($id, false);
        $user       = $this->currentUser();

        if ((int) $user->role !== User::ROLE_ADMIN && (int) $attachment->user_id !== (int) $user->id) {
            throw new BizException('只能删除自己上传的附件', 403, 403);
        }

        $proposal = $attachment->proposal;

        if ($proposal && (int) $proposal->status !== PackProposal::STATUS_DRAFT) {
            throw new BizException('方案已提交，不能删除附件');
        }

        if ($proposal) {
            ProposalService::assertEditable($proposal, $user);
        }

        $path = AttachmentModel::diskPath((string) $attachment->file_path);

        // 去重场景：仅按物理路径（file_path）统计引用，
        // 避免历史记录 md5 相同但文件已丢失时阻止当前文件被清理
        $referenced = AttachmentModel::where('file_path', (string) $attachment->file_path)
            ->where('id', '<>', (int) $attachment->id)
            ->count();

        if ($referenced === 0 && is_file($path)) {
            @unlink($path);
        }

        if ($proposal) {
            AuditService::record((int) $user->id, 'delete_attachment', (int) $attachment->proposal_id, [
                'version' => (int) $proposal->version,
                'content' => '删除附件：' . $attachment->file_name,
            ]);
        }

        $attachment->delete();

        return $this->success([], '附件已删除');
    }

    /**
     * 绑定暂存附件到方案（新增方案保存后调用）
     * @return Json
     * @throws BizException
     * @throws \Throwable
     */
    public function bind(): Json
    {
        $user = $this->currentUser();

        if ((int) $user->role === User::ROLE_REVIEWER) {
            throw new BizException('评审员不能操作附件', 403, 403);
        }

        $proposalId = (int) $this->request->param('proposal_id', 0);
        $ids        = $this->request->param('ids', []);
        $attachType = (string) $this->request->param('attach_type', '');

        if ($attachType !== '' && !isset(AttachmentModel::TYPE_TEXT[$attachType])) {
            throw new BizException('附件类型不正确');
        }

        if ($proposalId <= 0 || !is_array($ids) || empty($ids)) {
            return $this->success(['count' => 0]);
        }

        $proposal = PackProposal::find($proposalId);

        if (!$proposal) {
            throw new BizException('方案不存在或已被删除', 0, 404);
        }

        ProposalService::assertEditable($proposal, $user);

        $this->assertImageLimit($proposalId, $ids, $attachType);

        $count = Db::transaction(function () use ($ids, $proposalId, $proposal, $user, $attachType) {
            $update = ['proposal_id' => $proposalId];

            if ($attachType !== '') {
                $update['attach_type'] = $attachType;
            }

            $count = AttachmentModel::whereIn('id', array_map('intval', $ids))
                ->where('proposal_id', 0)
                ->where('user_id', (int) $user->id)
                ->update($update);

            if ($count > 0) {
                AuditService::record((int) $user->id, 'upload', $proposalId, [
                    'version' => (int) $proposal->version,
                    'content' => '关联附件 ' . $count . ' 个',
                ]);
            }

            return $count;
        });

        return $this->success(['count' => $count], '附件关联成功');
    }

    /**
     * 每个图片位置只允许 1 张：校验本次绑定不会造成重复
     * @param int    $proposalId
     * @param array  $ids
     * @param string $attachType 绑定指定的类型，空字符串表示沿用附件自身类型
     * @return void
     * @throws BizException
     */
    protected function assertImageLimit(int $proposalId, array $ids, string $attachType): void
    {
        $query = AttachmentModel::whereIn('id', array_map('intval', $ids))
            ->where('proposal_id', 0);

        if ($attachType !== '') {
            $query->where('attach_type', $attachType);
        }

        $counts = [];

        foreach ($query->column('attach_type') as $type) {
            $type          = (string) $type;
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        foreach ($counts as $type => $incoming) {
            if (!in_array($type, AttachmentModel::IMAGE_TYPES, true)) {
                continue;
            }

            $exists = AttachmentModel::where('proposal_id', $proposalId)
                ->where('attach_type', $type)
                ->count();

            if ($exists > 0 || $incoming > 1) {
                throw new BizException(
                    AttachmentModel::TYPE_TEXT[$type] . '只能上传 1 张图片，请先删除原图片'
                );
            }
        }
    }

    /**
     * 查询附件并校验查看权限
     * @param int  $id
     * @param bool $ensureExists 是否要求物理文件存在（删除时物理文件可能已被清理）
     * @return AttachmentModel
     * @throws BizException
     */
    protected function findAttachment(int $id, bool $ensureExists = true): AttachmentModel
    {
        $attachment = AttachmentModel::find($id);

        if (!$attachment) {
            throw new BizException('附件不存在或已被删除', 0, 404);
        }

        // 暂存附件（未关联方案）仅上传人/管理员可访问
        if ((int) $attachment->proposal_id === 0) {
            $user = $this->currentUser();

            if ((int) $user->role !== User::ROLE_ADMIN && (int) $attachment->user_id !== (int) $user->id) {
                throw new BizException('无权访问该附件', 403, 403);
            }

            return $ensureExists ? $this->ensureFileExists($attachment) : $attachment;
        }

        $proposal = $attachment->proposal;

        if (!$proposal) {
            throw new BizException('附件所属方案不存在或已被删除', 0, 404);
        }

        ProposalService::assertViewable($proposal, $this->currentUser());

        return $ensureExists ? $this->ensureFileExists($attachment) : $attachment;
    }

    /**
     * 校验物理文件存在
     * @param AttachmentModel $attachment
     * @return AttachmentModel
     * @throws BizException
     */
    protected function ensureFileExists(AttachmentModel $attachment): AttachmentModel
    {
        if (!is_file($this->absolutePath($attachment))) {
            throw new BizException('文件不存在或已被清理', 0, 404);
        }

        return $attachment;
    }

    /**
     * 附件绝对路径
     * @param AttachmentModel $attachment
     * @return string
     */
    protected function absolutePath(AttachmentModel $attachment): string
    {
        return AttachmentModel::diskPath((string) $attachment->file_path);
    }
}
